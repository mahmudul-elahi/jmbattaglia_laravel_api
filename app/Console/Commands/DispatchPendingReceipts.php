<?php

namespace App\Console\Commands;

use App\Jobs\SendBookingReceipt;
use App\Models\OrderSubmission;
use Illuminate\Console\Command;

class DispatchPendingReceipts extends Command
{
    protected $signature = 'portal:dispatch-receipts';

    protected $description = 'Dispatch durable pending booking receipt notifications';

    public function handle(): int
    {
        if (config('portal.mail_disabled')) {
            return self::SUCCESS;
        }
        OrderSubmission::query()->where('status', 'SUBMITTED')->where('notification_pending', true)->whereNull('notification_sent_at')->select('id')->chunkById(100, function ($orders): void {
            foreach ($orders as $order) {
                SendBookingReceipt::dispatch($order->id)->onQueue('notifications');
            }
        });

        return self::SUCCESS;
    }
}
