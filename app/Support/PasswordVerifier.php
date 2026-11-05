<?php

namespace App\Support;

use Illuminate\Support\Facades\Hash;

final class PasswordVerifier
{
    public static function check(#[\SensitiveParameter] string $password, ?string $hash): bool
    {
        if (! $hash || strlen($password) > 72) {
            return false;
        }
        if (preg_match('/^\$2[ab]\$[0-9]{2}\$[.\/A-Za-z0-9]{53}$/D', $hash)) {
            return password_verify($password, $hash);
        }
        try {
            return Hash::check($password, $hash);
        } catch (\RuntimeException) {
            return false;
        }
    }
}
