<?php

namespace Database\Factories;

use App\Models\CatalogItem;
use App\Models\RecommendationRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RecommendationRule> */
class RecommendationRuleFactory extends Factory
{
    protected $model = RecommendationRule::class;

    public function definition(): array
    {
        return ['item_id' => CatalogItem::factory(), 'reason' => 'Recommended for this property.', 'conditions' => ['requiresPoolSpa' => true]];
    }
}
