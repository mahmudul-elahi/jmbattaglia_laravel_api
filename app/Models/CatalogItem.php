<?php

namespace App\Models;

use App\Enums\CatalogKind;
use App\Enums\InspectionCategory;
use App\Enums\PackageType;
use App\Enums\PublicationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CatalogItem extends Model
{
    use HasFactory;

    protected $table = 'catalog_items';

    protected $fillable = ['slug', 'name', 'description', 'why_it_matters', 'features', 'category', 'kind', 'type', 'status', 'price_cents', 'currency', 'recommended', 'sort_order', 'isn_service_ids', 'isn_package_id', 'isn_order_type_id', 'version'];

    protected function casts(): array
    {
        return ['price_cents' => 'integer', 'version' => 'integer', 'sort_order' => 'integer', 'kind' => CatalogKind::class, 'category' => InspectionCategory::class, 'type' => PackageType::class, 'status' => PublicationStatus::class, 'features' => 'array', 'isn_service_ids' => 'array', 'recommended' => 'boolean'];
    }

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(MediaAsset::class, 'catalog_media', 'item_id', 'media_id')->withPivot('position')->orderByPivot('position');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'PUBLISHED');
    }
}
