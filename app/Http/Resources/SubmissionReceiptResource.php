<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubmissionReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['orderId' => (int) $this->id, 'isnOrderId' => $this->remote_order_id, 'isnOrderNumber' => $this->remote_order_number, 'notificationStatus' => $this->notification_sent_at ? 'SENT' : ($this->status?->value === 'SUBMITTED' ? 'PENDING' : 'NOT_QUEUED'), 'message' => match ($this->status?->value) {
            'SUBMITTED' => 'Your inspection request has been submitted. Our team will confirm your time and inspector details.','PROCESSING' => 'Your request is processing. Retry only the same request and key.','FAILED' => 'Your request failed before order creation. Keep this reference and contact our team.',default => 'Your request needs staff review. Keep this reference and do not submit again.'
        }, 'id' => (int) $this->id, 'reference' => $this->reference, 'status' => $this->status, 'appointmentStatus' => 'AWAITING_STAFF_CONFIRMATION', 'quoteId' => $this->quote->preview_token, 'totalCents' => $this->quote->total_cents, 'currency' => 'USD', 'date' => $this->request_data['date'], 'time' => $this->request_data['time'], 'timezone' => config('isn.timezone'), 'remoteOrderId' => $this->remote_order_id, 'remoteOrderNumber' => $this->remote_order_number, 'notificationPending' => $this->notification_pending, 'notificationSentAt' => $this->notification_sent_at?->toIso8601String(), 'errorCode' => $this->error_code];
    }
}
