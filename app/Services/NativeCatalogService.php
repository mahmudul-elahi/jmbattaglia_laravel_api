<?php

namespace App\Services;

use App\Enums\CatalogKind;
use App\Enums\InspectionCategory;
use App\Enums\PackageType;
use App\Enums\PublicationStatus;
use App\Models\CatalogItem;
use App\Support\PortalSerializer;
use Illuminate\Support\Str;

final class NativeCatalogService
{
    public function __construct(
        public IsnGatewayInterface $gateway,
        private MediaService $media
    ) {}

    public function catalog(bool $public = true): array
    {
        $catalog = $this->gateway->catalog();
        if (! $public) {
            return $catalog;
        }
        foreach ($catalog as $key => $rows) {
            $catalog[$key] = array_values(array_filter($rows, fn (array $row): bool => ($row['visible'] ?? false) && ($key !== 'packages' || $row['active'])));
        }

        return $catalog;
    }

    public function list(array $query, bool $public = false, ?string $kind = null): array
    {
        $catalog = $this->catalog($public);

        // Load all local package metadata
        $localPackages = CatalogItem::query()
            ->where('kind', CatalogKind::Package)
            ->with('media')
            ->get();
        $localByIsnId = $localPackages->whereNotNull('isn_package_id')->keyBy('isn_package_id');
        $localByName = $localPackages->keyBy(fn ($item) => strtolower(trim($item->name)));

        $rows = [];
        $seenIsnIds = [];

        // 1. Enriched ISN packages
        foreach ($catalog['packages'] as $package) {
            $seenIsnIds[] = $package['id'];
            $local = $localByIsnId->get($package['id']) ?? $localByName->get(strtolower(trim($package['name'])));
            $rows[] = $this->formatPackage($package['id'], $local, $public, $package) + ['kind' => 'PACKAGE'];
        }

        // 2. Local-only packages (not found in remote ISN)
        foreach ($localPackages as $local) {
            if ($local->isn_package_id && in_array($local->isn_package_id, $seenIsnIds, true)) {
                continue;
            }
            if ($public && $local->status !== PublicationStatus::Published) {
                continue;
            }
            $pkgId = $local->isn_package_id ?: 'local-'.$local->id;
            $rows[] = $this->formatPackage($pkgId, $local, $public) + ['kind' => 'PACKAGE'];
        }

        // 3. ISN Services
        foreach ($catalog['services'] as $service) {
            $rows[] = $service + ['kind' => $service['ancillary'] ? 'ADDON' : 'SERVICE'];
        }

        // Sort packages: newly created or updated packages first, then by sequence
        usort($rows, function ($a, $b) {
            if (($a['kind'] ?? '') === 'PACKAGE' && ($b['kind'] ?? '') === 'PACKAGE') {
                $timeA = max(
                    isset($a['updatedAt']) && $a['updatedAt'] ? strtotime($a['updatedAt']) : 0,
                    isset($a['createdAt']) && $a['createdAt'] ? strtotime($a['createdAt']) : 0
                );
                $timeB = max(
                    isset($b['updatedAt']) && $b['updatedAt'] ? strtotime($b['updatedAt']) : 0,
                    isset($b['createdAt']) && $b['createdAt'] ? strtotime($b['createdAt']) : 0
                );
                if ($timeA !== $timeB) {
                    return $timeB <=> $timeA;
                }

                return ($a['sequence'] ?? 0) <=> ($b['sequence'] ?? 0);
            }

            return 0;
        });

        $kind ??= $query['kind'] ?? null;
        $category = $query['category'] ?? null;
        $status = isset($query['status']) ? strtoupper((string) $query['status']) : null;
        $search = mb_strtolower(trim($query['search'] ?? ''));

        $rows = array_values(array_filter($rows, function ($row) use ($kind, $search, $category, $status) {
            if ($kind && ($row['kind'] ?? null) !== $kind) {
                return false;
            }
            if ($category && isset($row['category']) && $row['category'] !== $category) {
                return false;
            }
            if ($status && isset($row['status']) && strtoupper((string) $row['status']) !== $status) {
                return false;
            }
            if ($search && ! str_contains(mb_strtolower($row['name'] ?? ''), $search)) {
                return false;
            }

            return true;
        }));

        $page = $query['page'] ?? 1;
        $limit = $query['limit'] ?? 20;

        return [
            'items' => array_slice($rows, ($page - 1) * $limit, $limit),
            'total' => count($rows),
            'page' => $page,
            'limit' => $limit,
        ];
    }

    public function entry(string $id, bool $public): array
    {
        $catalog = $this->catalog($public);
        foreach ($catalog['packages'] as $package) {
            if ($package['id'] === $id) {
                $local = CatalogItem::query()
                    ->where('kind', CatalogKind::Package)
                    ->where(fn ($q) => $q->where('isn_package_id', $id)->orWhere('name', $package['name']))
                    ->with('media')
                    ->first();

                return $this->formatPackage($id, $local, $public, $package) + ['kind' => 'PACKAGE'];
            }
        }
        foreach ($catalog['services'] as $service) {
            if ($service['id'] === $id) {
                return $service + ['kind' => $service['ancillary'] ? 'ADDON' : 'SERVICE'];
            }
        }

        // Check local-only package
        $local = CatalogItem::query()
            ->where('kind', CatalogKind::Package)
            ->where(fn ($q) => $q->where('isn_package_id', $id)->orWhere('id', is_numeric($id) ? (int) $id : 0))
            ->with('media')
            ->first();
        if ($local) {
            return $this->formatPackage($id, $local, $public) + ['kind' => 'PACKAGE'];
        }

        abort(404, 'ISN catalog item not found.');
    }

    public function package(string $id, bool $public): array
    {
        $local = CatalogItem::query()
            ->where('kind', CatalogKind::Package)
            ->where(fn ($q) => $q->where('isn_package_id', $id)->orWhere('id', is_numeric($id) ? (int) $id : 0))
            ->with('media')
            ->first();

        return $this->formatPackage($id, $local, $public);
    }

    public function createPackage(array $input, int $actorId): array
    {
        // 1. Handle file upload if present
        $mediaIds = $input['mediaIds'] ?? [];
        foreach (['file', 'media', 'image'] as $key) {
            if (isset($input[$key]) && request()->hasFile($key)) {
                $uploaded = $this->media->upload(request()->file($key), $actorId);
                $mediaIds = array_values(array_unique(array_merge([$uploaded->id], $mediaIds)));
            }
        }

        // 2. Resolve services
        $services = $input['services'] ?? $input['serviceIds'] ?? [];

        // 3. Determine or create ISN package
        $packageId = null;
        try {
            $catalog = $this->gateway->catalog();
            if (! empty($catalog['packages'])) {
                foreach ($catalog['packages'] as $p) {
                    if (strcasecmp(trim($p['name']), trim($input['name'])) === 0) {
                        $packageId = $p['id'];
                        break;
                    }
                }
            }
        } catch (\Throwable) {
        }

        if (! $packageId && config('isn.writes_enabled')) {
            try {
                $res = $this->gateway->writePackage(null, [
                    'name' => $input['name'],
                    'description' => $input['description'] ?? '',
                    'best' => $input['best'] ?? $input['recommended'] ?? false,
                    'active' => $input['active'] ?? true,
                    'show' => $input['show'] ?? (($input['status'] ?? 'PUBLISHED') === 'PUBLISHED'),
                    'sequence' => $input['sequence'] ?? 0,
                    'services' => $services,
                ]);
                $packageId = $res['id'] ?? null;
            } catch (\Throwable) {
            }
        }

        if (! $packageId) {
            $packageId = (string) Str::uuid();
        }

        // 4. Save local metadata in catalog_items
        $catalogItem = CatalogItem::query()
            ->where('kind', CatalogKind::Package)
            ->where(fn ($q) => $q->where('isn_package_id', $packageId)->orWhere('name', $input['name']))
            ->first();

        if (! $catalogItem) {
            $catalogItem = new CatalogItem;
            $catalogItem->kind = CatalogKind::Package;
            $catalogItem->isn_package_id = $packageId;
            $catalogItem->slug = Str::slug($input['name']).'-'.substr($packageId, 0, 8);
        }

        $catalogItem->name = $input['name'];
        $catalogItem->description = $input['description'] ?? $input['name'];
        $catalogItem->why_it_matters = $input['whyItMatters'] ?? '';
        $catalogItem->features = $input['features'] ?? [];
        $catalogItem->category = InspectionCategory::tryFrom($input['category'] ?? '') ?? InspectionCategory::ResaleHome;
        $catalogItem->type = PackageType::tryFrom($input['type'] ?? '') ?? PackageType::Bundle;
        $catalogItem->status = ($input['status'] ?? 'PUBLISHED') === 'DRAFT' ? PublicationStatus::Draft : PublicationStatus::Published;
        $catalogItem->price_cents = (int) ($input['priceCents'] ?? 0);
        $catalogItem->recommended = (bool) ($input['recommended'] ?? $input['best'] ?? false);
        $catalogItem->isn_service_ids = $services;
        $catalogItem->save();

        if (! empty($mediaIds)) {
            $catalogItem->media()->sync($mediaIds);
        }

        return $this->formatPackage($packageId, $catalogItem->load('media'), false);
    }

    public function updatePackage(string $id, array $input, int $actorId): array
    {
        // 1. Handle file upload if present
        $mediaIds = $input['mediaIds'] ?? null;
        foreach (['file', 'media', 'image'] as $key) {
            if (isset($input[$key]) && request()->hasFile($key)) {
                $uploaded = $this->media->upload(request()->file($key), $actorId);
                $mediaIds = array_values(array_unique(array_merge([$uploaded->id], $mediaIds ?? [])));
            }
        }

        // 2. Resolve services
        $services = $input['services'] ?? $input['serviceIds'] ?? null;

        // 3. Update remote ISN package if writes are enabled
        if (config('isn.writes_enabled')) {
            try {
                $isnPayload = array_filter([
                    'name' => $input['name'] ?? null,
                    'description' => $input['description'] ?? null,
                    'active' => $input['active'] ?? null,
                    'show' => $input['show'] ?? (isset($input['status']) ? $input['status'] === 'PUBLISHED' : null),
                    'best' => $input['best'] ?? $input['recommended'] ?? null,
                    'sequence' => $input['sequence'] ?? null,
                    'services' => $services,
                ], fn ($v) => $v !== null);

                if (! empty($isnPayload)) {
                    $this->gateway->writePackage($id, $isnPayload);
                }
            } catch (\Throwable) {
            }
        }

        // 4. Update local metadata
        $catalogItem = CatalogItem::query()
            ->where('kind', CatalogKind::Package)
            ->where(fn ($q) => $q->where('isn_package_id', $id)->orWhere('id', is_numeric($id) ? (int) $id : 0))
            ->first();

        if (! $catalogItem && isset($input['name'])) {
            $catalogItem = CatalogItem::query()
                ->where('kind', CatalogKind::Package)
                ->where('name', $input['name'])
                ->first();
        }

        if (! $catalogItem) {
            $catalogItem = new CatalogItem;
            $catalogItem->kind = CatalogKind::Package;
            $catalogItem->isn_package_id = $id;

            $isn = null;
            try {
                $isn = $this->gateway->package($id);
            } catch (\Throwable) {
            }

            $catalogItem->name = $input['name'] ?? $isn['name'] ?? 'Package';
            $catalogItem->slug = Str::slug($catalogItem->name).'-'.substr($id, 0, 8);
            $catalogItem->description = $input['description'] ?? $isn['description'] ?? $catalogItem->name;
            $catalogItem->isn_service_ids = $services ?? $isn['serviceIds'] ?? [];
            $catalogItem->features = $input['features'] ?? [];
            $catalogItem->category = InspectionCategory::tryFrom($input['category'] ?? '') ?? InspectionCategory::ResaleHome;
            $catalogItem->type = PackageType::tryFrom($input['type'] ?? '') ?? PackageType::Bundle;
            $catalogItem->status = ($input['status'] ?? '') === 'DRAFT' ? PublicationStatus::Draft : PublicationStatus::Published;
            $catalogItem->price_cents = (int) ($input['priceCents'] ?? 0);
            $catalogItem->recommended = (bool) ($input['recommended'] ?? $isn['best'] ?? false);
        }

        if (isset($input['name'])) {
            $catalogItem->name = $input['name'];
        }
        if (isset($input['description'])) {
            $catalogItem->description = $input['description'];
        }
        if (isset($input['whyItMatters'])) {
            $catalogItem->why_it_matters = $input['whyItMatters'];
        }
        if (isset($input['features'])) {
            $catalogItem->features = $input['features'];
        }
        if (isset($input['category'])) {
            $catalogItem->category = InspectionCategory::tryFrom($input['category']) ?? $catalogItem->category;
        }
        if (isset($input['type'])) {
            $catalogItem->type = PackageType::tryFrom($input['type']) ?? $catalogItem->type;
        }
        if (isset($input['status'])) {
            $catalogItem->status = $input['status'] === 'DRAFT' ? PublicationStatus::Draft : PublicationStatus::Published;
        }
        if (isset($input['priceCents'])) {
            $catalogItem->price_cents = (int) $input['priceCents'];
        }
        if (isset($input['recommended'])) {
            $catalogItem->recommended = (bool) $input['recommended'];
        }
        if ($services !== null) {
            $catalogItem->isn_service_ids = $services;
        } elseif (! is_array($catalogItem->isn_service_ids)) {
            $catalogItem->isn_service_ids = [];
        }
        $catalogItem->save();

        if ($mediaIds !== null) {
            $catalogItem->media()->sync($mediaIds);
        }

        return $this->formatPackage($id, $catalogItem->load('media'), false);
    }

    public function deletePackage(string $id): array
    {
        $local = CatalogItem::query()
            ->where('kind', CatalogKind::Package)
            ->where(fn ($q) => $q->where('isn_package_id', $id)->orWhere('id', is_numeric($id) ? (int) $id : 0))
            ->first();

        if ($local) {
            $local->media()->detach();
            $local->delete();
        }

        if (config('isn.writes_enabled')) {
            try {
                return $this->gateway->writePackage($id, [], true);
            } catch (\Throwable $e) {
                if (! $local) {
                    throw $e;
                }
            }
        } elseif (! $local) {
            abort(503, 'ISN writes are disabled in .env (ISN_WRITES_ENABLED=false). Remote ISN packages cannot be deleted until writes are enabled.');
        }

        return ['id' => $id, 'deleted' => true];
    }

    public function formatPackage(string $id, ?CatalogItem $local, bool $public = false, ?array $isnPkg = null): array
    {
        if (! $isnPkg) {
            try {
                $isnPkg = $this->gateway->package($id);
            } catch (\Throwable) {
                $isnPkg = null;
            }
        }

        if (! $isnPkg && ! $local) {
            abort(404, 'Package not found.');
        }

        $mediaList = $local ? $local->media->map(fn ($m) => PortalSerializer::model($m))->all() : [];
        $primaryMedia = $mediaList[0] ?? null;
        $mediaUrl = $primaryMedia['url'] ?? null;
        $isVideo = $primaryMedia ? (str_starts_with($primaryMedia['mimeType'] ?? '', 'video/') || str_ends_with($mediaUrl ?? '', '.mp4')) : false;

        $serviceIds = $isnPkg['serviceIds'] ?? $local?->isn_service_ids ?? [];
        $services = [];
        try {
            $cat = $this->gateway->catalog();
            $catalogServices = collect($cat['services'] ?? [])->keyBy('id');
            foreach ($serviceIds as $sId) {
                $s = $catalogServices->get($sId);
                $services[] = [
                    'id' => $sId,
                    'name' => $s['name'] ?? 'Inspection Service',
                    'fee' => isset($s['priceCents']) && $s['priceCents'] !== null ? (string) ($s['priceCents'] / 100) : null,
                    'priceCents' => $s['priceCents'] ?? null,
                    'ancillary' => $s['ancillary'] ?? false,
                ];
            }
        } catch (\Throwable) {
            foreach ($serviceIds as $sId) {
                $services[] = [
                    'id' => $sId,
                    'name' => 'Inspection Service',
                    'fee' => null,
                    'priceCents' => null,
                    'ancillary' => false,
                ];
            }
        }

        $priceCents = $local?->price_cents ?? 0;
        if (! $priceCents && ! empty($services)) {
            $priceCents = (int) array_sum(array_filter(array_column($services, 'priceCents')));
        }
        $dollars = $priceCents / 100;

        return [
            'id' => $isnPkg['id'] ?? $local?->isn_package_id ?? $id,
            'name' => $isnPkg['name'] ?? $local?->name ?? 'Package',
            'active' => $isnPkg['active'] ?? ($local?->status?->value === 'PUBLISHED'),
            'visible' => $isnPkg['visible'] ?? ($local?->status?->value === 'PUBLISHED'),
            'serviceIds' => $serviceIds,
            'services' => $serviceIds,
            'description' => $isnPkg['description'] ?? $local?->description ?? '',
            'best' => $isnPkg['best'] ?? $local?->recommended ?? false,
            'sequence' => $isnPkg['sequence'] ?? 0,
            'modifierIds' => $isnPkg['modifierIds'] ?? [],
            'source' => $isnPkg ? 'ISN' : 'LOCAL',
            'category' => $local?->category?->value ?? (str_contains(strtolower($isnPkg['name'] ?? ''), 'new construction') ? 'NEW_CONSTRUCTION' : 'RESALE_HOME'),
            'type' => $local?->type?->value ?? 'BUNDLE',
            'priceCents' => $priceCents,
            'price' => (string) $dollars,
            'formattedPrice' => '$'.number_format($dollars, 2),
            'bundlePrice' => '$'.number_format($dollars, 2),
            'whyItMatters' => $local?->why_it_matters ?? '',
            'features' => $local?->features ?? [],
            'media' => $mediaList,
            'mediaIds' => array_column($mediaList, 'id'),
            'mediaUrl' => $mediaUrl,
            'mediaId' => $primaryMedia['id'] ?? null,
            'mediaType' => $isVideo ? 'video' : 'image',
            'isVideo' => $isVideo,
            'videoUrl' => $isVideo ? $mediaUrl : null,
            'imageUrl' => ! $isVideo ? $mediaUrl : null,
            'image' => ! $isVideo ? $mediaUrl : null,
            'status' => $local?->status?->value ?? (($isnPkg['visible'] ?? false) ? 'PUBLISHED' : 'DRAFT'),
            'recommended' => $local?->recommended ?? $isnPkg['best'] ?? false,
            'localCatalogId' => $local?->id,
            'createdAt' => $local?->created_at?->toIso8601String(),
            'updatedAt' => $local?->updated_at?->toIso8601String(),
        ];
    }
}
