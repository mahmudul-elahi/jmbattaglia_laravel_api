<?php

namespace Database\Factories;

use App\Models\BookingCompletion;
use App\Models\BookingDraft;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BookingCompletion> */
class BookingCompletionFactory extends Factory
{
    protected $model = BookingCompletion::class;

    public function definition(): array
    {
        return ['booking_id' => BookingDraft::factory(), 'reference' => 'AE-HIST-'.bin2hex(random_bytes(8)), 'idempotency_key' => bin2hex(random_bytes(32)), 'payload' => [], 'estimated_total_cents' => 45000, 'remote_submission_key' => bin2hex(random_bytes(32))];
    }
}
