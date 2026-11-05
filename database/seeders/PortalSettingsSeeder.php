<?php

namespace Database\Seeders;

use App\Models\PortalSettings;
use Illuminate\Database\Seeder;

class PortalSettingsSeeder extends Seeder
{
    public function run(): void
    {
        PortalSettings::query()->firstOrCreate(['id' => 1]);
    }
}
