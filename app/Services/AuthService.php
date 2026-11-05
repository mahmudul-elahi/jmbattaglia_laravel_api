<?php

namespace App\Services;

use App\Contracts\Repositories\AccessTokenRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\User;
use App\Support\PasswordVerifier;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class AuthService
{
    public function __construct(private UserRepositoryInterface $users, private AccessTokenRepositoryInterface $tokens) {}

    public function login(array $input): array
    {
        $user = $this->users->findForLogin($input['email']);
        if (! $user || ! $user->password || ! PasswordVerifier::check($input['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => ['Invalid email or password.']]);
        }
        if (Hash::needsRehash($user->password)) {
            $user->password = $input['password'];
            $user->save();
        }
        $expiresAt = now()->addMinutes(max(1, (int) config('auth_tokens.ttl_minutes')));

        return ['accessToken' => $this->tokens->issue($user, $expiresAt), 'tokenType' => 'Bearer', 'expiresIn' => max(0, $expiresAt->getTimestamp() - now()->getTimestamp()), 'expiresAt' => $expiresAt->toIso8601String(), 'user' => $user];
    }

    public function register(array $input): User
    {
        abort_unless(config('portal.registration_enabled'), 403, 'Registration is disabled.');

        return $this->users->register($input);
    }
}
