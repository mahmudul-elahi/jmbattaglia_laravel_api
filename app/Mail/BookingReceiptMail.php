<?php

namespace App\Mail;

use App\Models\OrderSubmission;
use Carbon\Carbon;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class BookingReceiptMail extends Mailable
{
    public string $reference;

    public ?OrderSubmission $order = null;

    public string $customerName = 'Valued Client';

    public string $propertyAddress = '';

    public string $appointmentDate = '';

    public string $appointmentTime = '';

    public string $formattedTotal = '';

    public ?string $durationMinutes = null;

    public function __construct(OrderSubmission|string $order)
    {
        if ($order instanceof OrderSubmission) {
            $this->order = $order->loadMissing('quote');
            $this->reference = $order->reference;
            $data = $order->request_data ?? [];
            $contact = ($data['customerType'] ?? '') === 'BUYERS_AGENT'
                ? ($data['buyerContact'] ?? $data['contact'] ?? [])
                : ($data['contact'] ?? []);

            $this->customerName = trim(($contact['firstName'] ?? '').' '.($contact['lastName'] ?? '')) ?: 'Valued Client';

            $prop = $order->quote?->property ?? $data['property'] ?? [];
            $addressParts = array_filter([
                $prop['address1'] ?? '',
                $prop['address2'] ?? '',
                trim(($prop['city'] ?? '').', '.($prop['state'] ?? '').' '.($prop['zip'] ?? '')),
            ]);
            $this->propertyAddress = implode(', ', $addressParts);

            if (! empty($data['date'])) {
                try {
                    $this->appointmentDate = Carbon::parse($data['date'])->format('l, F j, Y');
                } catch (\Throwable) {
                    $this->appointmentDate = $data['date'];
                }
            }

            if (! empty($data['time'])) {
                try {
                    $this->appointmentTime = Carbon::parse($data['time'])->format('g:i A');
                } catch (\Throwable) {
                    $this->appointmentTime = $data['time'];
                }
            }

            if ($order->quote && $order->quote->total_cents) {
                $this->formattedTotal = '$'.number_format($order->quote->total_cents / 100, 2);
            }

            if ($order->quote && $order->quote->duration_minutes) {
                $this->durationMinutes = (string) $order->quote->duration_minutes;
            }
        } else {
            $this->reference = (string) $order;
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'At Ease Inspection Request Confirmation ['.$this->reference.']');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.booking-receipt',
            text: 'mail.booking-receipt-text',
        );
    }
}
