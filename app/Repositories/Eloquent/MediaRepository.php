<?php

namespace App\Repositories\Eloquent;

use App\Models\MediaAsset;
use App\Models\UserProfile;
use App\Support\PortalSerializer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

final class MediaRepository
{
    public function create(array $attributes): MediaAsset
    {
        return MediaAsset::query()->create($attributes)->refresh();
    }

    public function find(int $id): MediaAsset
    {
        return MediaAsset::query()->findOrFail($id);
    }

    public function markReady(MediaAsset $media, array $attributes): MediaAsset
    {
        MediaAsset::query()->whereKey($media->id)->where('status', 'PENDING')->where('expires_at', '>', now())->update($attributes + ['status' => 'READY']);
        $media->refresh();
        abort_unless($media->status?->value === 'READY', 409, 'Upload is no longer pending or has expired.');

        return $media;
    }

    public function markDeleted(MediaAsset $media): void
    {
        $media->update(['status' => 'DELETED']);
    }

    public function expiredPending(): void
    {
        MediaAsset::query()->where('status', 'PENDING')->where('expires_at', '<', now())->update(['status' => 'DELETED']);
    }

    public function retired(int $days): LazyCollection
    {
        return MediaAsset::query()->where('status', 'DELETED')->where('updated_at', '<', now()->subDays($days))->lazyById(100);
    }

    public function referenced(MediaAsset $media): bool
    {
        return DB::table('catalog_media')->where('media_id', $media->id)->exists() || DB::table('content_media')->where('media_id', $media->id)->exists() || UserProfile::query()->where('avatar_url', $media->url)->exists();
    }

    public function readyPendingKeys(): array
    {
        return MediaAsset::query()->where('status', 'READY')->where('object_key', 'like', 'portal/pending/%')->pluck('object_key')->all();
    }

    public function listing(int $page, int $limit): array
    {
        $items = MediaAsset::query()->where('status', '!=', 'DELETED')->orderByDesc('id')->paginate($limit, ['*'], 'page', $page);

        return ['items' => $items->getCollection()->map(fn ($media) => PortalSerializer::model($media)), 'total' => $items->total(), 'page' => $items->currentPage(), 'limit' => $items->perPage()];
    }

    public function delete(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $media = MediaAsset::query()->lockForUpdate()->findOrFail($id);
            $referenced = DB::table('catalog_media')->where('media_id', $id)->exists() || DB::table('content_media')->where('media_id', $id)->exists() || UserProfile::query()->where('avatar_url', $media->url)->exists();
            abort_if($referenced, 409, 'Media is still referenced.');
            $media->update(['status' => 'DELETED']);
        });
    }
}
