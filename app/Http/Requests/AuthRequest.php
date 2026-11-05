<?php

namespace App\Http\Requests;

class AuthRequest extends StrictRequest
{
    public function rules(): array
    {
        $email = ['required', 'email', 'max:254'];

        return match ($this->route()->getActionMethod()) {
            'login' => ['email' => $email, 'password' => ['required', 'string', 'max:4096']],
            'register' => ['fullName' => ['required', 'string', 'min:2', 'max:100'], 'email' => $email, 'password' => $this->passwordRules(), 'confirmPassword' => ['required', 'same:password']],
            'verifyReset','verifyEmail' => ['email' => $email, 'otp' => ['required', 'string', 'regex:/^[0-9]{6}$/']],
            'reset' => ['resetToken' => ['required', 'string', 'size:64'], 'newPassword' => $this->passwordRules(), 'confirmPassword' => ['required', 'same:newPassword']],
            default => ['email' => $email],
        };
    }
}
