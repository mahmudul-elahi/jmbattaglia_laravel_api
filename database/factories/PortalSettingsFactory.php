<?php

namespace Database\Factories;

use App\Models\PortalSettings;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PortalSettings> */
class PortalSettingsFactory extends Factory
{
    protected $model = PortalSettings::class;

    public function definition(): array
    {
        return ['brand_name' => 'At Ease Inspections'];
    }
}
