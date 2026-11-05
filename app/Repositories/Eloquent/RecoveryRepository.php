<?php

namespace App\Repositories\Eloquent;

use App\Models\Otp;
use App\Models\PasswordResetGrant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class RecoveryRepository
{
    public function issue(string $email, string $type): ?array
    {
        return DB::transaction(function () use ($email, $type): ?array {
            $user = User::query()->where('email', $email)->when($type === 'PASSWORD_RESET', fn ($query) => $query->where('provider', 'local')->whereNotNull('password'))->lockForUpdate()->first();
            if (! $user) {
                return null;
            }
            if ($type === 'EMAIL_VERIFICATION' && $user->email_verified_at) {
                return null;
            }
            $recent = Otp::query()->where('user_id', $user->id)->where('type', $type)->where('created_at', '>', now()->subMinute())->exists();
            if ($recent) {
                return null;
            }
            Otp::query()->where('user_id', $user->id)->where('type', $type)->whereNull('consumed_at')->update(['consumed_at' => now()]);
            $code = (string) random_int(100000, 999999);
            Otp::query()->create(['user_id' => $user->id, 'type' => $type, 'otp_hash' => Hash::make($code), 'expires_at' => now()->addMinutes(10)]);

            return ['email' => $user->email, 'code' => $code];
        });
    }

    public function consume(string $email, string $type, string $code): ?User
    {
        return DB::transaction(function () use ($email, $type, $code): ?User {
            $user = User::query()->where('email', $email)->when($type === 'PASSWORD_RESET', fn ($query) => $query->where('provider', 'local')->whereNotNull('password'))->lockForUpdate()->first();
            if (! $user) {
                return null;
            }
            $otp = Otp::query()->where('user_id', $user->id)->where('type', $type)->whereNull('consumed_at')->latest('id')->lockForUpdate()->first();
            if (! $otp || $otp->expires_at->isPast() || $otp->attempts >= $otp->max_attempts) {
                return null;
            }
            $otp->increment('attempts');
            if (! Hash::check($code, $otp->otp_hash)) {
                return null;
            }
            $otp->update(['consumed_at' => now()]);
            if ($type === 'EMAIL_VERIFICATION') {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            return $user;
        });
    }

    public function grant(User $user): string
    {
        $token = bin2hex(random_bytes(32));
        PasswordResetGrant::query()->create(['user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addMinutes(10)]);

        return $token;
    }

    public function reset(string $token, string $password): bool
    {
        return DB::transaction(function () use ($token, $password): bool {
            $grant = PasswordResetGrant::query()->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            if (! $grant || $grant->consumed_at || $grant->expires_at->isPast()) {
                return false;
            }
            $user = User::query()->lockForUpdate()->findOrFail($grant->user_id);
            if ($user->provider !== 'local' || ! $user->password) {
                return false;
            }
            $user->password = $password;
            $user->save();
            $user->tokens()->delete();
            PasswordResetGrant::query()->where('user_id', $user->id)->whereNull('consumed_at')->update(['consumed_at' => now()]);
            Otp::query()->where('user_id', $user->id)->whereNull('consumed_at')->update(['consumed_at' => now()]);

            return true;
        });
    }
}
