<?php

namespace Database\Factories;

use App\Models\BookingDraft;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BookingDraft> */
class BookingDraftFactory extends Factory
{
    protected $model = BookingDraft::class;

    public function definition(): array
    {
        return ['token_hash' => hash('sha256', bin2hex(random_bytes(32))), 'data' => [], 'expires_at' => now()->addDay()];
    }
}
