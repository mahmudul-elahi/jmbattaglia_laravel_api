<?php

namespace App\Repositories\Sanctum;

use App\Contracts\Repositories\AccessTokenRepositoryInterface;
use App\Models\User;
use Carbon\CarbonInterface;

final class AccessTokenRepository implements AccessTokenRepositoryInterface
{
    public function issue(User $user, CarbonInterface $expiresAt): string
    {
        return $user->createToken('portal-admin', ['*'], $expiresAt)->plainTextToken;
    }

    public function revokeCurrent(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    public function revokeAll(User $user): void
    {
        $user->tokens()->delete();
    }
}
