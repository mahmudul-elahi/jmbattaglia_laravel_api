<?php

namespace App\Models;

use App\Enums\OtpType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    use HasFactory;

    protected $table = 'otps';

    protected $fillable = ['user_id', 'otp_hash', 'type', 'expires_at', 'attempts', 'max_attempts', 'consumed_at'];

    protected function casts(): array
    {
        return ['type' => OtpType::class, 'expires_at' => 'immutable_datetime', 'consumed_at' => 'immutable_datetime'];
    }

    protected $hidden = ['otp_hash', 'token_hash'];
}
