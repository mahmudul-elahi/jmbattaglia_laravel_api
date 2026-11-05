<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PasswordResetGrant extends Model
{
    use HasFactory;

    protected $table = 'password_reset_grants';

    protected $fillable = ['user_id', 'token_hash', 'expires_at', 'consumed_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'consumed_at' => 'immutable_datetime'];
    }

    protected $hidden = ['otp_hash', 'token_hash'];
}
