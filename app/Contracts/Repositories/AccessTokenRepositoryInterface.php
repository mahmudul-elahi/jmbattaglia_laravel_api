<?php

namespace App\Contracts\Repositories;

use App\Models\User;
use Carbon\CarbonInterface;

interface AccessTokenRepositoryInterface
{
    public function issue(User $user, CarbonInterface $expiresAt): string;

    public function revokeCurrent(User $user): void;

    public function revokeAll(User $user): void;
}
