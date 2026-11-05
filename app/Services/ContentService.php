<?php

namespace App\Services;

use App\Models\CatalogItem;
use App\Models\ContentEntry;
use App\Models\MediaAsset;
use App\Models\PortalSettings;
use App\Models\RecommendationRule;
use App\Support\PortalSerializer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class ContentService
{
    public function __construct(private MediaService $media) {}

    public function publishedAddons(array $ids, string $category): Collection
    {
        return CatalogItem::query()
            ->whereIn('id', $ids)
            ->where('kind', 'ADDON')
            ->where('status', 'PUBLISHED')
            ->where('category', $category)
            ->get()
            ->keyBy('id');
    }

    public function listing(string $type, array $filters, bool $public): array
    {
        $query = $this->query($type)->with('media');
        if ($public) {
            $query->where('status', 'PUBLISHED');
        }
        if (isset($filters['search'])) {
            $query->where(fn ($q) => $q->whereRaw('LOWER('.($type === 'content' ? 'title' : 'name').') LIKE ?', ['%'.mb_strtolower($filters['search']).'%']));
        }
        foreach (['category', 'kind', 'status'] as $key) {
            if (isset($filters[$key])) {
                $query->where($key, $filters[$key]);
            }
        }
        $page = $query->orderBy('sort_order')->orderBy('id')->paginate($filters['limit'] ?? 20, ['*'], 'page', $filters['page'] ?? 1);

        return [
            'items' => array_map(fn (Model $model): array => $public ? PortalSerializer::publicModel($model) : PortalSerializer::model($model), $page->items()),
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'limit' => $page->perPage(),
            'totalPages' => $page->lastPage(),
        ];
    }

    public function find(string $type, int $id, bool $public = false): Model
    {
        return $this->query($type)->when($public, fn ($query) => $query->where('status', 'PUBLISHED'))->with('media')->findOrFail($id);
    }

    public function save(string $type, ?int $id, array $input, int $actor): Model
    {
        $uploaded = null;
        if ($type === 'addons') {
            foreach (['file', 'media', 'image'] as $key) {
                if (isset($input[$key])) {
                    abort_if($uploaded !== null, 400, 'Upload one media file.');
                    $uploaded = $this->media->upload($input[$key], $actor);
                    $input['mediaIds'] = array_merge([$uploaded->id], $input['mediaIds'] ?? []);
                }
            }
            $input = Arr::except($input, ['price', 'mediaId', 'isnServiceId', 'file', 'media', 'image']);
        }
        try {
            return DB::transaction(function () use ($type, $id, $input): Model {
                $model = $id ? $this->query($type)->lockForUpdate()->findOrFail($id) : ($type === 'content' ? new ContentEntry : new CatalogItem);
                $version = $input['version'] ?? ($type === 'addons' && $id ? $model->version : null);
                unset($input['version']);
                if ($id) {
                    abort_unless($model->version === $version, 409, 'This record has changed. Reload before saving.');
                }
                $media = $input['mediaIds'] ?? null;
                unset($input['mediaIds']);
                if ($media !== null) {
                    abort_unless(MediaAsset::query()->whereIn('id', $media)->where('status', 'READY')->lockForUpdate()->get(['id'])->count() === count($media), 400, 'Only READY media may be attached.');
                }
                $attributes = PortalSerializer::attributes($input);
                if (! $id && $type === 'addons') {
                    $attributes += ['features' => [], 'isn_service_ids' => [], 'why_it_matters' => ''];
                }
                $model->fill($attributes);
                if ($model->status?->value === 'PUBLISHED') {
                    $this->assertPublishable($model);
                }
                if ($id) {
                    $updated = $this->query($type)->whereKey($id)->where('version', $version)->update($attributes + ['version' => $version + 1]);
                    abort_unless($updated === 1, 409, 'This record has changed.');
                    $model->refresh();
                } else {
                    $model->save();
                    $model->refresh();
                }
                if ($media !== null) {
                    $model->media()->sync(collect($media)->mapWithKeys(fn (int $mediaId, int $position): array => [$mediaId => ['position' => $position]])->all());
                }

                return $model->load('media');
            });
        } catch (\Throwable $error) {
            if ($uploaded) {
                $uploaded->update(['status' => 'DELETED']);
            }
            throw $error;
        }
    }

    public function transition(string $type, int $id, int $version, string $status, int $actor): Model
    {
        return $this->save($type, $id, ['version' => $version, 'status' => $status], $actor);
    }

    public function deleteAddon(int $id, ?int $version, bool $permanent, int $actor): void
    {
        DB::transaction(function () use ($id, $version, $permanent, $actor): void {
            $model = CatalogItem::query()->where('kind', 'ADDON')->lockForUpdate()->findOrFail($id);
            $version ??= $model->version;
            abort_unless($model->version === $version, 409, 'This record has changed.');
            if ($permanent) {
                $model->delete();
            } else {
                $this->transition('addons', $id, $version, 'ARCHIVED', $actor);
            }
        });
    }

    public function dashboardMetadata(): array
    {
        return CatalogItem::query()->with('media')->orderByDesc('created_at')->orderByDesc('id')->get()->map(fn ($model) => PortalSerializer::model($model))->all();
    }

    public function settings(): PortalSettings
    {
        return PortalSettings::query()->findOrFail(1);
    }

    public function updateSettings(array $input, int $actor): PortalSettings
    {
        return DB::transaction(function () use ($input): PortalSettings {
            $version = $input['version'];
            unset($input['version']);
            $count = PortalSettings::query()->whereKey(1)->where('version', $version)->update(PortalSerializer::attributes($input) + ['version' => $version + 1]);
            abort_unless($count === 1, 409, 'Settings have changed. Reload before saving.');

            return $this->settings();
        });
    }

    public function rules(): Collection
    {
        return RecommendationRule::query()->with('item.media')->orderBy('sort_order')->orderBy('id')->get();
    }

    public function createRule(array $input, int $actor): RecommendationRule
    {
        return DB::transaction(function () use ($input): RecommendationRule {
            return RecommendationRule::query()->create(PortalSerializer::attributes($input))->refresh();
        });
    }

    public function deleteRule(int $id, int $actor): void
    {
        DB::transaction(function () use ($id): void {
            RecommendationRule::query()->findOrFail($id)->delete();
        });
    }

    public function recommend(array $input): array
    {
        $property = $input['property'];
        $items = [];
        foreach ($this->rules()->where('enabled', true) as $rule) {
            if (in_array($rule->item_id, $input['selectedIds'] ?? [], true)) {
                continue;
            }
            $matches = true;
            foreach ($rule->conditions as $key => $expected) {
                $matches = $matches && match ($key) {
                    'requiresPoolSpa' => ($property['poolSpa'] ?? false) === $expected,
                    'waterSystem' => ($property['waterSystem'] ?? null) === $expected,
                    'builtBefore' => isset($property['yearBuilt']) && $property['yearBuilt'] < $expected,
                    'foundationType' => ($property['foundationType'] ?? null) === $expected,
                    default => false
                };
            }
            if (! $matches) {
                continue;
            }
            $item = $rule->item;
            if ($item && ($item->status?->value !== 'PUBLISHED' || $item->category?->value !== $input['category'])) {
                $item = null;
            }
            if ($item) {
                $items[] = ['item' => PortalSerializer::publicModel($item), 'reason' => $rule->reason];
            }
        }

        return $items;
    }

    private function query(string $type): Builder
    {
        return $type === 'content' ? ContentEntry::query() : CatalogItem::query()->where('kind', 'ADDON');
    }

    private function assertPublishable(Model $model): void
    {
        if ($model instanceof ContentEntry) {
            abort_if(in_array($model->kind?->value, ['EDUCATION', 'TOOLTIP']) && trim($model->body) === '', 400, 'Text is required for publication.');
            abort_if($model->kind?->value === 'RESOURCE' && (! filter_var($model->url, FILTER_VALIDATE_URL) || ! str_starts_with($model->url, 'https://')), 400, 'An HTTPS resource URL is required.');
        }
    }
}
