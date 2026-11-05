<?php

namespace Database\Factories;

use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MediaAsset> */
class MediaAssetFactory extends Factory
{
    protected $model = MediaAsset::class;

    public function definition(): array
    {
        return ['object_key' => 'portal/'.bin2hex(random_bytes(16)).'.png', 'filename' => 'fixture.png', 'mime_type' => 'image/png', 'size_bytes' => 100, 'checksum' => base64_encode(hash('sha256', 'fixture', true)), 'url' => 'https://cdn.example.com/'.bin2hex(random_bytes(16)).'.png', 'status' => 'READY', 'created_by' => User::factory(), 'expires_at' => now()->addMinutes(10)];
    }
}
