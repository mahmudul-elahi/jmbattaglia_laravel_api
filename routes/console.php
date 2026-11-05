<?php

use App\Jobs\SendBookingReceipt;
use App\Models\BookingCompletion;
use App\Models\OrderSubmission;
use App\Models\Otp;
use App\Models\PasswordResetGrant;
use App\Models\QuoteSnapshot;
use App\Services\LegacyImportService;
use App\Services\MediaService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::command('portal:cleanup')->daily()->withoutOverlapping();
Schedule::command('portal:dispatch-receipts')->everyMinute()->withoutOverlapping();
Schedule::command('portal:recover-stalled')->everyFiveMinutes()->withoutOverlapping();

Artisan::command('portal:cleanup', function (MediaService $media): int {
    Otp::query()->where('expires_at', '<', now()->subDays(7))->delete();
    PasswordResetGrant::query()->where('expires_at', '<', now()->subDays(7))->delete();
    QuoteSnapshot::query()->where('expires_at', '<', now()->subDays(7))->whereNotIn('id', OrderSubmission::query()->select('quote_id'))->delete();

    $media->cleanup((int) config('portal.media_retention_days', 7));

    return 0;
})->purpose('Remove expired recovery credentials and unreferenced stale quote snapshots');

Artisan::command('portal:dispatch-receipts', function (): int {
    if (config('portal.mail_disabled')) {
        return 0;
    }
    OrderSubmission::query()->where('status', 'SUBMITTED')->where('notification_pending', true)->whereNull('notification_sent_at')->select('id')->chunkById(100, function ($orders): void {
        foreach ($orders as $order) {
            SendBookingReceipt::dispatch($order->id)->onQueue('notifications');
        }
    });

    return 0;
})->purpose('Dispatch durable pending booking receipt notifications');

Artisan::command('portal:recover-stalled', function (): int {
    OrderSubmission::query()->where('status', 'PROCESSING')->where('updated_at', '<', now()->subMinutes(10))->update(['status' => 'REQUIRES_REVIEW', 'error_code' => 'STALLED_WORKFLOW']);
    BookingCompletion::query()->where('status', 'PROCESSING')->where('lease_expires_at', '<', now())->update(['status' => 'REQUIRES_REVIEW', 'error_code' => 'EXPIRED_SYNC_LEASE', 'lease_id' => null, 'lease_expires_at' => null]);

    return 0;
})->purpose('Surface stalled workflow attempts for review without retrying remote writes');

Artisan::command('portal:import {file : JSON export keyed by Prisma model name} {--dry-run : Validate and roll back all writes}', function (LegacyImportService $importer): int {
    $file = $this->argument('file');
    if (! is_file($file) || ! is_readable($file)) {
        $this->error('Import file is not readable.');

        return 1;
    }
    try {
        $input = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($input)) {
            throw new InvalidArgumentException('Expected a JSON object keyed by Prisma model name.');
        }
        $counts = $importer->import($input, (bool) $this->option('dry-run'));
        $this->table(['Entity', 'Imported'], collect($counts)->map(fn ($count, $entity) => [$entity, $count])->values()->all());

        return 0;
    } catch (Throwable $error) {
        $this->error('Import failed. All changes were rolled back. Verify source references and the target schema.');

        return 1;
    }
})->purpose('Import a source export into integer-ID tables without replaying credentials or remote writes');
