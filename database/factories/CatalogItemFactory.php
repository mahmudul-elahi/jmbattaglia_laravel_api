<?php

namespace Database\Factories;

use App\Models\CatalogItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CatalogItem> */
class CatalogItemFactory extends Factory
{
    protected $model = CatalogItem::class;

    public function definition(): array
    {
        return ['slug' => fake()->unique()->slug(), 'name' => fake()->words(3, true), 'description' => fake()->sentence(), 'why_it_matters' => 'Useful inspection information.', 'features' => [], 'category' => 'RESALE_HOME', 'kind' => 'ADDON', 'type' => 'SINGLE', 'status' => 'DRAFT', 'price_cents' => 7900, 'isn_service_ids' => []];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['status' => 'PUBLISHED']);
    }
}
