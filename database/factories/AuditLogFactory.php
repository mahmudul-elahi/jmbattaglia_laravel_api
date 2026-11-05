<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AuditLog> */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return ['actor_id' => User::factory(), 'action' => 'UPDATE', 'entity_type' => 'content', 'local_entity_id' => 1];
    }
}
