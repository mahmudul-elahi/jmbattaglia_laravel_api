<?php

namespace App\Services;

final class NativeCatalogService
{
    public function __construct(public IsnGatewayInterface $gateway) {}

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
        $rows = [];
        foreach ($catalog['packages'] as $package) {
            $rows[] = $package + ['kind' => 'PACKAGE'];
        }
        foreach ($catalog['services'] as $service) {
            $rows[] = $service + ['kind' => $service['ancillary'] ? 'ADDON' : 'SERVICE'];
        }
        $kind ??= $query['kind'] ?? null;
        $search = mb_strtolower(trim($query['search'] ?? ''));
        $rows = array_values(array_filter($rows, fn ($row) => (! $kind || $row['kind'] === $kind) && (! $search || str_contains(mb_strtolower($row['name']), $search))));
        $page = $query['page'] ?? 1;
        $limit = $query['limit'] ?? 20;

        return ['items' => array_slice($rows, ($page - 1) * $limit, $limit), 'total' => count($rows), 'page' => $page, 'limit' => $limit];
    }

    public function entry(string $id, bool $public): array
    {
        $catalog = $this->catalog($public);
        foreach ($catalog['packages'] as $package) {
            if ($package['id'] === $id) {
                return $package + ['kind' => 'PACKAGE'];
            }
        }
        foreach ($catalog['services'] as $service) {
            if ($service['id'] === $id) {
                return $service + ['kind' => $service['ancillary'] ? 'ADDON' : 'SERVICE'];
            }
        }
        abort(404, 'ISN catalog item not found.');
    }

    public function package(string $id, bool $public): array
    {
        $row = $this->gateway->package($id);
        abort_if($public && (! $row['active'] || ! $row['visible']), 404);

        return $row;
    }
}
