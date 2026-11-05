<?php

namespace App\Http\Requests;

class PackageRequest extends StrictRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        if ($this->isMethod('GET') || $this->isMethod('DELETE')) {
            return;
        }

        $data = $this->all();

        // 1. Resolve services & serviceIds (handle single string, comma-separated, JSON, or array)
        $rawServices = $data['services'] ?? $data['serviceIds'] ?? null;
        if (is_string($rawServices)) {
            $decoded = json_decode($rawServices, true);
            if (is_array($decoded)) {
                $rawServices = $decoded;
            } else {
                $rawServices = array_filter(array_map('trim', explode(',', $rawServices)));
            }
        }
        if (is_array($rawServices)) {
            $data['services'] = array_values(array_filter($rawServices));
            $data['serviceIds'] = $data['services'];
        }

        // 2. Resolve features (handle JSON string, newline delimited, or array)
        if (isset($data['features']) && is_string($data['features'])) {
            $decoded = json_decode($data['features'], true);
            if (is_array($decoded)) {
                $data['features'] = $decoded;
            } else {
                $data['features'] = array_filter(array_map('trim', explode("\n", $data['features'])));
            }
        } elseif (isset($data['features']) && ! is_array($data['features'])) {
            $data['features'] = [(string) $data['features']];
        }

        // 3. Normalize booleans
        foreach (['recommended', 'active', 'show', 'best'] as $key) {
            if (isset($data[$key]) && in_array($data[$key], ['true', 'false', '1', '0', 1, 0], true)) {
                $data[$key] = in_array($data[$key], ['true', '1', 1], true);
            }
        }

        // 4. Normalize price & priceCents
        if (! isset($data['priceCents']) && isset($data['price'])) {
            $clean = str_replace(['$', ','], '', (string) $data['price']);
            if (preg_match('/^[0-9]+(?:[.][0-9]{1,2})?$/', $clean)) {
                $parts = explode('.', $clean);
                $whole = (int) $parts[0];
                $data['priceCents'] = str_contains($clean, '.') || $whole < 1000 ? $whole * 100 + (int) str_pad($parts[1] ?? '', 2, '0') : $whole;
            }
        }
        if (isset($data['priceCents']) && is_numeric($data['priceCents'])) {
            $data['priceCents'] = (int) $data['priceCents'];
        }

        // 5. Normalize type & category to uppercase enum values
        if (isset($data['type'])) {
            $typeUpper = strtoupper((string) $data['type']);
            $data['type'] = in_array($typeUpper, ['SINGLE', 'BUNDLE'], true) ? $typeUpper : 'BUNDLE';
        }
        if (isset($data['category'])) {
            $catUpper = strtoupper(str_replace(' ', '_', (string) $data['category']));
            $data['category'] = in_array($catUpper, ['RESALE_HOME', 'NEW_CONSTRUCTION'], true) ? $catUpper : 'RESALE_HOME';
        }

        // 6. Normalize status
        if (isset($data['status'])) {
            $data['status'] = strtoupper((string) $data['status']);
        }

        // 7. Normalize mediaId & mediaIds
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

        $this->replace($data);
    }

    public function rules(): array
    {
        if ($this->isMethod('GET')) {
            return [
                'search' => 'sometimes|string|max:100',
                'kind' => 'sometimes|in:PACKAGE,SERVICE,ADDON',
                'tab' => 'sometimes|in:packages,add-ons,addons',
                'category' => 'sometimes|in:RESALE_HOME,NEW_CONSTRUCTION',
                'page' => 'sometimes|integer|min:1',
                'limit' => 'sometimes|integer|min:1|max:100',
            ];
        }
        if ($this->isMethod('DELETE')) {
            return [];
        }

        return [
            // Core ISN package fields
            'name' => ($this->route()->getActionMethod() === 'store' ? 'required' : 'sometimes').'|string|min:1|max:150',
            'description' => 'sometimes|nullable|string|max:5000',
            'active' => 'sometimes|boolean',
            'show' => 'sometimes|boolean',
            'best' => 'sometimes|boolean',
            'sequence' => 'sometimes|integer|min:0|max:10000',
            'services' => 'sometimes|array|max:100',
            'services.*' => 'string|max:100|distinct',
            'serviceIds' => 'sometimes|array|max:100',
            'serviceIds.*' => 'string|max:100|distinct',
            'modifiers' => 'sometimes|array|max:100',
            'modifiers.*' => 'string|max:100|distinct',

            // 3-Step Wizard Local Metadata
            'type' => 'sometimes|in:SINGLE,BUNDLE',
            'category' => 'sometimes|in:RESALE_HOME,NEW_CONSTRUCTION',
            'priceCents' => 'sometimes|integer|min:0|max:100000000',
            'price' => 'sometimes|regex:/^[\$0-9,.]+$/',
            'whyItMatters' => 'sometimes|nullable|string|max:5000',
            'features' => 'sometimes|array|max:50',
            'features.*' => 'string|max:250',
            'recommended' => 'sometimes|boolean',
            'status' => 'sometimes|in:DRAFT,PUBLISHED,ARCHIVED',
            'mediaId' => 'sometimes|nullable|integer|exists:media_assets,id',
            'mediaIds' => 'sometimes|array|max:10',
            'mediaIds.*' => 'integer|distinct|exists:media_assets,id',
            'file' => 'sometimes|file|mimetypes:image/jpeg,image/png,video/mp4|max:51200',
            'media' => 'sometimes|file|mimetypes:image/jpeg,image/png,video/mp4|max:51200',
            'image' => 'sometimes|file|mimetypes:image/jpeg,image/png|max:51200',
            'videoUrl' => 'sometimes|nullable|string|max:1000',
            'version' => 'sometimes|integer|min:1',
            '_method' => 'sometimes|string|in:PATCH,PUT',
        ];
    }
}
