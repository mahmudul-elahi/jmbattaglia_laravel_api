<?php

namespace App\Jobs;

use App\Mail\BookingReceiptMail;
use App\Models\OrderSubmission;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class SendBookingReceipt implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public int $orderId) {}

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(): void
    {
        if (config('portal.mail_disabled')) {
            return;
        }
        Cache::lock('receipt:'.$this->orderId, 90)->block(5, function (): void {
            $order = OrderSubmission::query()->findOrFail($this->orderId);
            if (! $order->notification_pending || $order->notification_sent_at || $order->status?->value !== 'SUBMITTED') {
                return;
            }
            $input = $order->request_data;
            $buyer = $input['customerType'] === 'BUYERS_AGENT' ? $input['buyerContact'] : $input['contact'];
            Mail::to($buyer['email'])->send(new BookingReceiptMail($order->reference));
            $order->update(['notification_pending' => false, 'notification_sent_at' => now()]);
        });
    }
}
