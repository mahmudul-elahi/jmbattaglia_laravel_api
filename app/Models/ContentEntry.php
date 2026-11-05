<?php

namespace App\Models;

use App\Enums\ContentKind;
use App\Enums\InspectionCategory;
use App\Enums\PublicationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ContentEntry extends Model
{
    use HasFactory;

    protected $table = 'content_entries';

    protected $fillable = ['key', 'kind', 'title', 'body', 'url', 'category', 'sort_order', 'status', 'version'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'sort_order' => 'integer', 'kind' => ContentKind::class, 'category' => InspectionCategory::class, 'status' => PublicationStatus::class];
    }

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(MediaAsset::class, 'content_media', 'entry_id', 'media_id')->withPivot('position')->orderByPivot('position');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'PUBLISHED');
    }
}
