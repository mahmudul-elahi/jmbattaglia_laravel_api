<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UserProfile> */
class UserProfileFactory extends Factory
{
    protected $model = UserProfile::class;

    public function definition(): array
    {
        return ['user_id' => User::factory(), 'first_name' => fake()->firstName(), 'last_name' => fake()->lastName()];
    }
}
