<?php

namespace Database\Factories;

use App\Models\QuoteSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuoteSnapshot> */
class QuoteSnapshotFactory extends Factory
{
    protected $model = QuoteSnapshot::class;

    public function definition(): array
    {
        return ['preview_token' => bin2hex(random_bytes(32)), 'category' => 'RESALE_HOME', 'property' => [], 'catalog_ids' => [], 'catalog_versions' => [], 'service_ids' => ['provider-service'], 'order_type_id' => 'provider-type', 'lines' => [['id' => 'fee', 'amountCents' => 45000]], 'total_cents' => 45000, 'duration_minutes' => 180, 'expires_at' => now()->addMinutes(30)];
    }
}
