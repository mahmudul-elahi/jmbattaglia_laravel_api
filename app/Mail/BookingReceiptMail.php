<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class BookingReceiptMail extends Mailable
{
    public function __construct(public string $reference) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'At Ease request '.$this->reference);
    }

    public function content(): Content
    {
        return new Content(text: 'mail.booking-receipt');
    }
}
