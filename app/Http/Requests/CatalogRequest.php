<?php

namespace App\Http\Requests;

use Illuminate\Support\Str;

class CatalogRequest extends StrictRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        if ($this->isMethod('GET')) {
            return;
        }
        $data = $this->all();
        foreach (['features', 'isnServiceIds', 'mediaIds'] as $key) {
            if (isset($data[$key]) && is_string($data[$key])) {
                $decoded = json_decode($data[$key], true);
                if (is_array($decoded)) {
                    $data[$key] = $decoded;
                }
            }
        }
        foreach (['recommended', 'permanent'] as $key) {
            if (isset($data[$key]) && in_array($data[$key], ['true', 'false'], true)) {
                $data[$key] = $data[$key] === 'true';
            }
        }
        if (! isset($data['priceCents']) && isset($data['price'])) {
            $clean = str_replace(['$', ','], '', (string) $data['price']);
            if (preg_match('/^[0-9]+(?:[.][0-9]{1,2})?$/', $clean)) {
                $parts = explode('.', $clean);
                $whole = (int) $parts[0];
                $data['priceCents'] = str_contains($clean, '.') || $whole < 1000 ? $whole * 100 + (int) str_pad($parts[1] ?? '', 2, '0') : $whole;
            }
        }
        // Normalize mediaIds & mediaId
        $mediaIds = [];
        if (isset($data['mediaIds'])) {
            if (is_string($data['mediaIds'])) {
                $decoded = json_decode($data['mediaIds'], true);
                if (is_array($decoded)) {
                    $mediaIds = array_map('intval', $decoded);
                } elseif (is_numeric($data['mediaIds'])) {
                    $mediaIds = [(int) $data['mediaIds']];
                } else {
                    $mediaIds = array_map('intval', array_filter(array_map('trim', explode(',', $data['mediaIds']))));
                }
            } elseif (is_array($data['mediaIds'])) {
                $mediaIds = array_map('intval', $data['mediaIds']);
            }
        }
        if (isset($data['mediaId']) && is_numeric($data['mediaId'])) {
            $mediaIds[] = (int) $data['mediaId'];
        }
        if (! empty($mediaIds)) {
            $data['mediaIds'] = array_values(array_unique($mediaIds));
        } else {
            unset($data['mediaIds']);
        }

        // Normalize isnServiceIds & isnServiceId
        $isnServiceIds = [];
        if (isset($data['isnServiceIds'])) {
            if (is_string($data['isnServiceIds'])) {
                $decoded = json_decode($data['isnServiceIds'], true);
                if (is_array($decoded)) {
                    $isnServiceIds = $decoded;
                } else {
                    $isnServiceIds = array_filter(array_map('trim', explode(',', $data['isnServiceIds'])));
                }
            } elseif (is_array($data['isnServiceIds'])) {
                $isnServiceIds = $data['isnServiceIds'];
            }
        }
        if (isset($data['isnServiceId']) && is_string($data['isnServiceId']) && $data['isnServiceId'] !== '') {
            $isnServiceIds[] = $data['isnServiceId'];
        }
        if (! empty($isnServiceIds)) {
            $data['isnServiceIds'] = array_values(array_unique($isnServiceIds));
        } else {
            unset($data['isnServiceIds']);
        }
        if ($this->isMethod('POST') && isset($data['name'])) {
            $data += ['slug' => Str::slug($data['name']) ?: 'addon-'.bin2hex(random_bytes(8)), 'description' => $data['name'], 'category' => 'RESALE_HOME', 'priceCents' => 0, 'status' => 'PUBLISHED'];
        }
        $this->replace($data);
    }

    public function rules(): array
    {
        if ($this->isMethod('GET')) {
            return ['search' => 'sometimes|string|max:100', 'page' => 'sometimes|integer|min:1|max:100000', 'limit' => 'sometimes|integer|min:1|max:100', 'category' => 'sometimes|in:RESALE_HOME,NEW_CONSTRUCTION', 'kind' => 'sometimes|in:ADDON', 'status' => $this->is('api/v1/admin/*') ? 'sometimes|in:DRAFT,PUBLISHED,ARCHIVED' : 'prohibited'];
        }
        if ($this->isMethod('DELETE')) {
            return ['version' => 'sometimes|integer|min:1', 'permanent' => 'sometimes|boolean'];
        }
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return ['slug' => $required.'|string|max:100|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'name' => $required.'|string|min:2|max:150', 'description' => $required.'|string|max:500', 'whyItMatters' => 'sometimes|string|max:5000', 'features' => 'sometimes|array|max:30', 'features.*' => 'string|max:250|distinct', 'category' => $required.'|in:RESALE_HOME,NEW_CONSTRUCTION', 'kind' => 'sometimes|in:ADDON', 'type' => 'sometimes|in:SINGLE,BUNDLE', 'priceCents' => $required.'|integer|min:0|max:100000000', 'recommended' => 'sometimes|boolean', 'sortOrder' => 'sometimes|integer|min:0|max:10000', 'status' => 'sometimes|in:DRAFT,PUBLISHED,ARCHIVED', 'isnServiceIds' => 'sometimes|array|max:30', 'isnServiceIds.*' => 'string|max:100|distinct', 'isnPackageId' => 'sometimes|nullable|string|max:100', 'isnOrderTypeId' => 'sometimes|nullable|string|max:100', 'mediaIds' => 'sometimes|array|max:10', 'mediaIds.*' => 'integer|distinct|exists:media_assets,id', 'price' => 'sometimes|regex:/^[\$0-9,.]+$/', 'mediaId' => 'sometimes|integer|exists:media_assets,id', 'isnServiceId' => 'sometimes|string|max:100', 'file' => 'sometimes|file|mimetypes:image/jpeg,image/png,video/mp4|max:51200', 'media' => 'sometimes|file|mimetypes:image/jpeg,image/png,video/mp4|max:51200', 'image' => 'sometimes|file|mimetypes:image/jpeg,image/png|max:51200', 'version' => $this->isMethod('PATCH') || $this->isMethod('PUT') ? 'sometimes|integer|min:1' : 'prohibited'];
    }
}
