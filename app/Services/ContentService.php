<?php

namespace App\Services;

use App\Repositories\Eloquent\ContentRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

final class ContentService
{
    public function __construct(public ContentRepository $repository, private MediaService $media) {}

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
            return $this->repository->save($type, $id, $input, $actor);
        } catch (\Throwable $error) {
            if ($uploaded) {
                $uploaded->update(['status' => 'DELETED']);
            } throw $error;
        }
    }
}
