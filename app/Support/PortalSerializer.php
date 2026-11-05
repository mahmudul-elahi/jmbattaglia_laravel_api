<?php

namespace App\Support;

use App\Models\ContentEntry;
use App\Models\PortalSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class PortalSerializer
{
    public static function model(Model $model): array
    {
        $data = [];
        foreach ($model->toArray() as $key => $value) {
            $data[Str::camel($key)] = $value;
        }

        return $data;
    }

    public static function publicModel(Model $model): array
    {
        $data = self::model($model);
        $fields = $model instanceof ContentEntry ? ['id', 'key', 'kind', 'title', 'body', 'url', 'category', 'sortOrder'] : ($model instanceof PortalSettings ? ['brandName', 'helpPhone', 'welcomeTitle', 'pricingDisclaimer', 'confirmationMessage'] : ['id', 'slug', 'name', 'description', 'whyItMatters', 'features', 'category', 'kind', 'type', 'priceCents', 'currency', 'recommended', 'sortOrder']);
        $result = Arr::only($data, $fields);
        if (array_key_exists('media', $data)) {
            $result['media'] = array_values(array_map(fn ($media) => Arr::only($media, ['id', 'url', 'alt_text', 'mime_type']), array_filter($data['media'], fn ($media) => $media['status'] === 'READY')));
            foreach ($result['media'] as &$media) {
                $media = self::camelArray($media);
            } unset($media);
        }

        return $result;
    }

    private static function camelArray(array $input): array
    {
        $data = [];
        foreach ($input as $key => $value) {
            $data[Str::camel($key)] = $value;
        }

return $data;
    }

    public static function attributes(array $input): array
    {
        $data = [];
        foreach ($input as $key => $value) {
            $data[Str::snake($key)] = $value;
        }

return $data;
    }
}
