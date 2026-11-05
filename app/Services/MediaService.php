<?php

namespace App\Services;

use App\Models\MediaAsset;
use App\Models\UserProfile;
use App\Support\PortalSerializer;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;

final class MediaService
{
    public function listing(int $page, int $limit): array
    {
        $items = MediaAsset::query()->where('status', '!=', 'DELETED')->orderByDesc('id')->paginate($limit, ['*'], 'page', $page);

        return [
            'items' => $items->getCollection()->map(fn ($media) => PortalSerializer::model($media)),
            'total' => $items->total(),
            'page' => $items->currentPage(),
            'limit' => $items->perPage(),
        ];
    }

    public function upload(UploadedFile $file, int $actor, string $altText = ''): MediaAsset
    {
        $mime = $file->getMimeType();
        $bytes = $file->get();
        $this->verifyBytes($bytes, $mime);
        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'video/mp4' => 'mp4',
            default => abort(400, 'Unsupported media type.')
        };
        $key = 'portal/'.bin2hex(random_bytes(32)).'.'.$extension;
        $disk = $this->disk();
        abort_unless($disk->put($key, $bytes), 503, 'Unable to store media.');
        try {
            return MediaAsset::query()->create([
                'object_key' => $key,
                'filename' => basename($file->getClientOriginalName()),
                'mime_type' => $mime,
                'size_bytes' => strlen($bytes),
                'checksum' => base64_encode(hash('sha256', $bytes, true)),
                'url' => $disk->url($key),
                'alt_text' => $altText,
                'status' => 'READY',
                'created_by' => $actor,
                'expires_at' => now()->addMinutes(10),
            ])->refresh();
        } catch (\Throwable $error) {
            $disk->delete($key);
            throw $error;
        }
    }

    public function signed(array $input, int $actor): array
    {
        abort_unless(config('portal.media_disk') === 's3', 503, 'Signed uploads require S3 storage.');
        $extension = match ($input['mimeType']) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => 'mp4'
        };
        $disk = $this->disk();
        $key = 'portal/pending/'.bin2hex(random_bytes(32)).'.'.$extension;
        $expiresAt = now()->addMinutes(10);
        try {
            $upload = $disk->temporaryUploadUrl($key, $expiresAt, ['ContentType' => $input['mimeType'], 'ChecksumSHA256' => $input['checksum']]);
        } catch (\Throwable) {
            abort(503, 'Storage signing is unavailable.');
        }
        $media = MediaAsset::query()->create([
            'object_key' => $key,
            'filename' => $input['filename'],
            'mime_type' => $input['mimeType'],
            'size_bytes' => $input['sizeBytes'],
            'checksum' => $input['checksum'],
            'url' => $disk->url($key),
            'alt_text' => $input['altText'] ?? '',
            'created_by' => $actor,
            'expires_at' => $expiresAt,
        ])->refresh();

        return [
            'media' => PortalSerializer::model($media),
            'uploadUrl' => $upload['url'],
            'headers' => $upload['headers'],
            'expiresAt' => $expiresAt->toIso8601String(),
        ];
    }

    public function complete(int $id): MediaAsset
    {
        $media = MediaAsset::query()->findOrFail($id);
        if ($media->status?->value === 'READY') {
            return $media;
        }
        abort_unless($media->status?->value === 'PENDING' && $media->expires_at->isFuture(), 409, 'Upload has expired.');
        $disk = $this->disk();
        abort_unless($disk->exists($media->object_key), 400, 'Uploaded object is missing.');
        abort_unless($disk->size($media->object_key) === $media->size_bytes, 400, 'Upload size does not match.');
        $bytes = $disk->get($media->object_key);
        abort_unless(is_string($bytes), 503, 'Unable to read uploaded media.');
        $this->verifyBytes($bytes, $media->mime_type);
        abort_unless(hash_equals($media->checksum, base64_encode(hash('sha256', $bytes, true))), 400, 'Upload checksum does not match.');

        $extension = match ($media->mime_type) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => 'mp4'
        };
        $finalKey = 'portal/ready/'.bin2hex(random_bytes(32)).'.'.$extension;
        abort_unless($disk->put($finalKey, $bytes), 503, 'Unable to finalize media.');
        try {
            MediaAsset::query()->whereKey($media->id)->where('status', 'PENDING')->where('expires_at', '>', now())->update([
                'object_key' => $finalKey,
                'url' => $disk->url($finalKey),
                'status' => 'READY',
            ]);
            $completed = $media->refresh();
            abort_unless($completed->status?->value === 'READY', 409, 'Upload is no longer pending or has expired.');
        } catch (\Throwable $error) {
            $disk->delete($finalKey);
            throw $error;
        }
        if ($completed->object_key !== $finalKey) {
            $disk->delete($finalKey);
        }

        return $completed;
    }

    public function cleanup(int $retentionDays = 7): void
    {
        $retentionDays = max(1, $retentionDays);
        $disk = $this->disk();
        MediaAsset::query()->where('status', 'PENDING')->where('expires_at', '<', now())->update(['status' => 'DELETED']);
        foreach ($this->retired($retentionDays) as $media) {
            if (! $this->referenced($media)) {
                $disk->delete($media->object_key);
            }
        }
        $protected = array_flip($this->readyPendingKeys());
        $cutoff = now()->subDays($retentionDays)->timestamp;
        foreach ($disk->getDriver()->listContents('portal/pending', true) as $entry) {
            if ($entry->isFile() && $entry->lastModified() !== null && $entry->lastModified() < $cutoff && ! isset($protected[$entry->path()])) {
                $disk->delete($entry->path());
            }
        }
    }

    public function delete(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $media = MediaAsset::query()->lockForUpdate()->findOrFail($id);
            $referenced = DB::table('catalog_media')->where('media_id', $id)->exists()
                || DB::table('content_media')->where('media_id', $id)->exists()
                || UserProfile::query()->where('avatar_url', $media->url)->exists();
            abort_if($referenced, 409, 'Media is still referenced.');
            $media->update(['status' => 'DELETED']);
        });
    }

    private function retired(int $days): LazyCollection
    {
        return MediaAsset::query()->where('status', 'DELETED')->where('updated_at', '<', now()->subDays($days))->lazyById(100);
    }

    private function referenced(MediaAsset $media): bool
    {
        return DB::table('catalog_media')->where('media_id', $media->id)->exists()
            || DB::table('content_media')->where('media_id', $media->id)->exists()
            || UserProfile::query()->where('avatar_url', $media->url)->exists();
    }

    private function readyPendingKeys(): array
    {
        return MediaAsset::query()->where('status', 'READY')->where('object_key', 'like', 'portal/pending/%')->pluck('object_key')->all();
    }

    private function disk(): FilesystemAdapter
    {
        if (config('portal.media_disk') === 's3') {
            abort_unless(config('filesystems.disks.s3.bucket') && config('filesystems.disks.s3.region'), 503, 'S3 storage is not configured.');
        }
        try {
            return Storage::disk(config('portal.media_disk'));
        } catch (\Throwable) {
            abort(503, 'Media storage is unavailable.');
        }
    }

    private function verifyBytes(string $bytes, string $mime): void
    {
        abort_if(strlen($bytes) > 50 * 1024 * 1024, 413, 'Media exceeds the size limit.');
        $actual = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $valid = match ($mime) {
            'image/jpeg' => str_starts_with($bytes, "\xFF\xD8\xFF") && @getimagesizefromstring($bytes) !== false,
            'image/png' => str_starts_with($bytes, "\x89PNG\r\n\x1A\n") && @getimagesizefromstring($bytes) !== false,
            'video/mp4' => strlen($bytes) >= 12 && substr($bytes, 4, 4) === 'ftyp',
            default => false,
        };
        abort_unless($valid && $actual === $mime, 400, 'Media signature does not match its declared type.');
    }
}
