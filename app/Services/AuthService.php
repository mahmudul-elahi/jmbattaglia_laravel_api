<?php

namespace App\Services;

use App\Models\User;
use App\Support\PasswordVerifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class AuthService
{
    public function login(array $input): array
    {
        $user = User::query()->with(['roles.permissions', 'permissions', 'profile'])->where('email', $input['email'])->first();
        if (! $user || ! $user->password || ! PasswordVerifier::check($input['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => ['Invalid email or password.']]);
        }
        if (Hash::needsRehash($user->password)) {
            $user->password = $input['password'];
            $user->save();
        }
        $expiresAt = now()->addMinutes(max(1, (int) config('auth_tokens.ttl_minutes')));

        return [
            'accessToken' => $user->createToken('portal-admin', ['*'], $expiresAt)->plainTextToken,
            'tokenType' => 'Bearer',
            'expiresIn' => max(0, $expiresAt->getTimestamp() - now()->getTimestamp()),
            'expiresAt' => $expiresAt->toIso8601String(),
            'user' => $user,
        ];
    }

    public function register(array $input): User
    {
        abort_unless(config('portal.registration_enabled'), 403, 'Registration is disabled.');

        return DB::transaction(function () use ($input): User {
            abort_if(User::query()->where('email', $input['email'])->exists(), 409, 'Email already registered.');
            $user = User::query()->create(['name' => $input['fullName'], 'email' => $input['email'], 'password' => $input['password']]);
            $user->profile()->create(['first_name' => $input['fullName']]);
            $user->syncRoles(['USER']);

            return $user->load(['roles.permissions', 'permissions', 'profile']);
        });
    }

    public function logout(?User $user): void
    {
        $user?->currentAccessToken()?->delete();
    }
}
