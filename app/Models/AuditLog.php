<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    protected $table = 'audit_logs';

    protected $fillable = ['actor_id', 'actor_label', 'action', 'entity_type', 'local_entity_id', 'external_entity_id'];

    protected function casts(): array
    {
        return [];
    }

    public const UPDATED_AT = null;
}
