<?php

namespace App\Console\Commands;

use App\Models\OrderSubmission;
use App\Models\Otp;
use App\Models\PasswordResetGrant;
use App\Models\QuoteSnapshot;
use App\Services\MediaService;
use Illuminate\Console\Command;

class CleanupPortalRecords extends Command
{
    protected $signature = 'portal:cleanup';

    protected $description = 'Remove expired recovery credentials and unreferenced stale quote snapshots';

    public function handle(MediaService $media): int
    {
        Otp::query()->where('expires_at', '<', now()->subDays(7))->delete();
        PasswordResetGrant::query()->where('expires_at', '<', now()->subDays(7))->delete();
        QuoteSnapshot::query()->where('expires_at', '<', now()->subDays(7))->whereNotIn('id', OrderSubmission::query()->select('quote_id'))->delete();

        $media->cleanup((int) config('portal.media_retention_days', 7));

        return self::SUCCESS;
    }
}
