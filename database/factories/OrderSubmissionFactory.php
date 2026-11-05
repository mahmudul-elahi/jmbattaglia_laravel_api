<?php

namespace Database\Factories;

use App\Models\OrderSubmission;
use App\Models\QuoteSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrderSubmission> */
class OrderSubmissionFactory extends Factory
{
    protected $model = OrderSubmission::class;

    public function definition(): array
    {
        return ['reference' => 'AE-FIXTURE-'.bin2hex(random_bytes(8)), 'idempotency_key' => bin2hex(random_bytes(32)), 'request_hash' => hash('sha256', 'fixture'), 'quote_id' => QuoteSnapshot::factory(), 'request_data' => ['date' => '2026-12-01', 'time' => '09:00', 'customerType' => 'HOME_BUYER', 'contact' => ['firstName' => 'Jamie', 'lastName' => 'Rivera', 'email' => 'buyer@example.com', 'phone' => '770-555-0100']], 'status' => 'SUBMITTED', 'phase' => 'ORDER_VERIFIED', 'notification_pending' => true];
    }
}
