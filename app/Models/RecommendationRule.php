<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecommendationRule extends Model
{
    use HasFactory;

    protected $table = 'recommendation_rules';

    protected $fillable = ['item_id', 'reason', 'conditions', 'enabled', 'sort_order'];

    protected function casts(): array
    {
        return ['item_id' => 'integer', 'sort_order' => 'integer', 'conditions' => 'array', 'enabled' => 'boolean'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'item_id');
    }
}
