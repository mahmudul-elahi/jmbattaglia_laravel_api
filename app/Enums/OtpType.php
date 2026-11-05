<?php

namespace App\Enums;

enum OtpType: string
{
    case EmailVerification = 'EMAIL_VERIFICATION';
    case PasswordReset = 'PASSWORD_RESET';
}
