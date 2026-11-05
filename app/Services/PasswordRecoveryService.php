<?php

namespace App\Services;

use App\Jobs\SendSecurityCode;
use App\Repositories\Eloquent\RecoveryRepository;

final class PasswordRecoveryService
{
    public function __construct(private RecoveryRepository $repository) {}

    public function send(string $email, string $type): void
    {
        $issued = $this->repository->issue($email, $type);
        if ($issued && ! config('portal.mail_disabled')) {
            SendSecurityCode::dispatch($issued['email'], $issued['code'], $type)->onQueue('mail')->afterCommit();
        }
    }

    public function verify(string $email, string $type, string $code): array
    {
        $user = $this->repository->consume($email, $type, $code);
        abort_unless($user, 400, 'Invalid or expired verification code.');

        return $type === 'PASSWORD_RESET' ? ['resetToken' => $this->repository->grant($user), 'expiresIn' => 600] : ['verified' => true];
    }

    public function reset(string $token, string $password): void
    {
        abort_unless($this->repository->reset($token, $password), 400, 'Invalid or expired reset token.');
    }
}
