<?php

namespace App\Jobs;

use App\Mail\SecurityCodeMail;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendSecurityCode implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public string $email, public string $code, public string $purpose) {}

    public function backoff(): array
    {
        return [10, 60, 120];
    }

    public function handle(): void
    {
        if (config('portal.mail_disabled')) {
            return;
        }
        if (config('mail.mailers.'.config('mail.default').'.transport') === 'log') {
            throw new \RuntimeException('Security code delivery requires a delivery transport.');
        }
        Mail::to($this->email)->send(new SecurityCodeMail($this->code));
    }
}
