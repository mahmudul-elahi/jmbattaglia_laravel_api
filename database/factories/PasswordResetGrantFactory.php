<?php

namespace Database\Factories;

use App\Models\PasswordResetGrant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PasswordResetGrant> */
class PasswordResetGrantFactory extends Factory
{
    protected $model = PasswordResetGrant::class;

    public function definition(): array
    {
        return ['user_id' => User::factory(), 'token_hash' => hash('sha256', bin2hex(random_bytes(32))), 'expires_at' => now()->addMinutes(10)];
    }
}
