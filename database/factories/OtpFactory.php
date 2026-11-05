<?php

namespace Database\Factories;

use App\Models\Otp;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/** @extends Factory<Otp> */
class OtpFactory extends Factory
{
    protected $model = Otp::class;

    public function definition(): array
    {
        return ['user_id' => User::factory(), 'otp_hash' => Hash::make('123456'), 'type' => 'PASSWORD_RESET', 'expires_at' => now()->addMinutes(10)];
    }
}
