<?php

namespace Database\Factories;

use App\Models\ContentEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContentEntry> */
class ContentEntryFactory extends Factory
{
    protected $model = ContentEntry::class;

    public function definition(): array
    {
        return ['key' => fake()->unique()->slug(), 'kind' => 'EDUCATION', 'title' => fake()->sentence(), 'body' => fake()->paragraph(), 'status' => 'DRAFT'];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['status' => 'PUBLISHED']);
    }
}
