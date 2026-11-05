<?php

namespace App\Models;

use App\Enums\InspectionCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class QuoteSnapshot extends Model
{
    use HasFactory;

    protected $table = 'quote_snapshots';

    protected $fillable = ['preview_token', 'pricing_source', 'category', 'property', 'catalog_ids', 'catalog_versions', 'service_ids', 'package_id', 'order_type_id', 'lines', 'total_cents', 'duration_minutes', 'expires_at'];

    protected function casts(): array
    {
        return ['total_cents' => 'integer', 'duration_minutes' => 'integer', 'category' => InspectionCategory::class, 'property' => 'array', 'catalog_ids' => 'array', 'catalog_versions' => 'array', 'service_ids' => 'array', 'lines' => 'array', 'expires_at' => 'immutable_datetime'];
    }

    public const UPDATED_AT = null;

    public function submission(): HasOne
    {
        return $this->hasOne(OrderSubmission::class, 'quote_id');
    }
}
