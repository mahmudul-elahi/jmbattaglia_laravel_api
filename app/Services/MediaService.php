<?php

namespace App\Services;

use App\Models\MediaAsset;
use App\Repositories\Eloquent\MediaRepository;
use App\Support\PortalSerializer;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class MediaService
{
    public function __construct(private MediaRepository $repository) {}

    public function listing(int $page, int $limit): array
    {
        return $this->repository->listing($page, $limit);
    }

    public function upload(UploadedFile $file, int $actor, string $altText = ''): MediaAsset
    {
        $mime = $file->getMimeType();
        $bytes = $file->get();
        $this->verifyBytes($bytes, $mime);
        $extension = match ($mime) {
            'image/jpeg' => 'jpg','image/png' => 'png','video/mp4' => 'mp4',default => abort(400, 'Unsupported media type.')
        };
        $key = 'portal/'.bin2hex(random_bytes(32)).'.'.$extension;
        $disk = $this->disk();
        abort_unless($disk->put($key, $bytes), 503, 'Unable to store media.');
        try {
            return $this->repository->create(['object_key' => $key, 'filename' => basename($file->getClientOriginalName()), 'mime_type' => $mime, 'size_bytes' => strlen($bytes), 'checksum' => base64_encode(hash('sha256', $bytes, true)), 'url' => $disk->url($key), 'alt_text' => $altText, 'status' => 'READY', 'created_by' => $actor, 'expires_at' => now()->addMinutes(10)]);
        } catch (\Throwable $error) {
            $disk->delete($key);
            throw $error;
        }
    }

    public function signed(array $input, int $actor): array
    {
        abort_unless(config('portal.media_disk') === 's3', 503, 'Signed uploads require S3 storage.');
        $extension = match ($input['mimeType']) {
            'image/jpeg' => 'jpg','image/png' => 'png',default => 'mp4'
        };
        $disk = $this->disk();
        $key = 'portal/pending/'.bin2hex(random_bytes(32)).'.'.$extension;
        $expiresAt = now()->addMinutes(10);
        try {
            $upload = $disk->temporaryUploadUrl($key, $expiresAt, ['ContentType' => $input['mimeType'], 'ChecksumSHA256' => $input['checksum']]);
        } catch (\Throwable) {
            abort(503, 'Storage signing is unavailable.');
        }
        $media = $this->repository->create(['object_key' => $key, 'filename' => $input['filename'], 'mime_type' => $input['mimeType'], 'size_bytes' => $input['sizeBytes'], 'checksum' => $input['checksum'], 'url' => $disk->url($key), 'alt_text' => $input['altText'] ?? '', 'created_by' => $actor, 'expires_at' => $expiresAt]);

        return ['media' => PortalSerializer::model($media), 'uploadUrl' => $upload['url'], 'headers' => $upload['headers'], 'expiresAt' => $expiresAt->toIso8601String()];
    }

    public function complete(int $id): MediaAsset
    {
        $media = $this->repository->find($id);
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
            'image/jpeg' => 'jpg','image/png' => 'png',default => 'mp4'
        };
        $finalKey = 'portal/ready/'.bin2hex(random_bytes(32)).'.'.$extension;
        abort_unless($disk->put($finalKey, $bytes), 503, 'Unable to finalize media.');
        try {
            $completed = $this->repository->markReady($media, ['object_key' => $finalKey, 'url' => $disk->url($finalKey)]);
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
        $this->repository->expiredPending();
        foreach ($this->repository->retired($retentionDays) as $media) {
            if (! $this->repository->referenced($media)) {
                $disk->delete($media->object_key);
            }
        }
        $protected = array_flip($this->repository->readyPendingKeys());
        $cutoff = now()->subDays($retentionDays)->timestamp;
        foreach ($disk->getDriver()->listContents('portal/pending', true) as $entry) {
            if ($entry->isFile() && $entry->lastModified() !== null && $entry->lastModified() < $cutoff && ! isset($protected[$entry->path()])) {
                $disk->delete($entry->path());
            }
        }
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
            'video/mp4' => strlen($bytes) >= 12 && substr($bytes, 4, 4) === 'ftyp',default => false,
        };
        abort_unless($valid && $actual === $mime, 400, 'Media signature does not match its declared type.');
    }

    public function delete(int $id): void
    {
        $this->repository->delete($id);
    }
}
