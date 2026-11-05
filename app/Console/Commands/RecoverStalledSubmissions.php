<?php

namespace App\Console\Commands;

use App\Models\BookingCompletion;
use App\Models\OrderSubmission;
use Illuminate\Console\Command;

class RecoverStalledSubmissions extends Command
{
    protected $signature = 'portal:recover-stalled';

    protected $description = 'Surface stalled workflow attempts for review without retrying remote writes';

    public function handle(): int
    {
        OrderSubmission::query()->where('status', 'PROCESSING')->where('updated_at', '<', now()->subMinutes(10))->update(['status' => 'REQUIRES_REVIEW', 'error_code' => 'STALLED_WORKFLOW']);
        BookingCompletion::query()->where('status', 'PROCESSING')->where('lease_expires_at', '<', now())->update(['status' => 'REQUIRES_REVIEW', 'error_code' => 'EXPIRED_SYNC_LEASE', 'lease_id' => null, 'lease_expires_at' => null]);

        return self::SUCCESS;
    }
}
