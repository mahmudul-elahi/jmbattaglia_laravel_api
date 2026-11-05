<?php

namespace App\Services;

use App\Enums\CatalogKind;
use App\Enums\PublicationStatus;
use App\Models\CatalogItem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

final class QuoteService implements QuotePreviewServiceInterface
{
    public function __construct(
        private IsnGatewayInterface $gateway,
        private ContentService $content
    ) {}

    public static function propertyPayload(array $p, bool $order = false): array
    {
        return [
            'address1' => trim($p['address1']),
            'address2' => trim($p['address2'] ?? ''),
            'city' => trim($p['city']),
            'state' => $p['state'],
            'zip' => $p['zip'],
            'squarefeet' => (string) $p['squareFeet'],
            'yearbuilt' => (string) $p['yearBuilt'],
            ($order ? 'foundationtypeuuid' : 'foundationtype') => $p['foundationType'],
        ];
    }

    public function put(array $preview): string
    {
        $token = bin2hex(random_bytes(32));
        Cache::store(config('portal.preview_store'))->put('portal:quote:'.$token, $preview, 1800);

        return $token;
    }

    public function find(string $token): ?array
    {
        $quote = Cache::store(config('portal.preview_store'))->get('portal:quote:'.$token);
        abort_unless($quote, 409, 'Quote has expired. Request a new quote.');

        return $quote;
    }

    public function create(array $input): array
    {
        $preview = $this->price($input);
        $token = $this->put($preview);

        return ['quoteId' => $token] + Arr::only($preview, ['lines', 'totalCents', 'durationMinutes', 'serviceIds', 'packageId', 'orderTypeId', 'expiresAt']) + ['currency' => 'USD', 'timezone' => config('isn.timezone'), 'pricingStatus' => 'ESTIMATE'];
    }

    public function price(array $input): array
    {
        $catalog = $this->gateway->catalog();
        $services = $input['serviceIds'] ?? [];
        $packageId = $input['packageId'] ?? null;
        $bundled = [];
        $local = [];
        if ($packageId) {
            // Check if packageId is an alias for the primary residential inspection service
            if (in_array($packageId, ['d0d3f4f4-d184-4168-9e87-725f37bda9e9', 'residential-home', 'bbbc1155-310f-11e3-80f0-22000afd1674'], true)) {
                $bundled[] = 'bbbc1155-310f-11e3-80f0-22000afd1674';
                $packageId = null;
                $input['packageId'] = null;
            } else {
                $package = collect($catalog['packages'])->firstWhere('id', $packageId);
                if (! $package) {
                    $localPkg = CatalogItem::query()
                        ->where('kind', CatalogKind::Package)
                        ->where(fn ($q) => $q->where('isn_package_id', $packageId)->orWhere('id', is_numeric($packageId) ? (int) $packageId : 0))
                        ->where('status', PublicationStatus::Published)
                        ->first();
                    if ($localPkg) {
                        $package = [
                            'id' => $localPkg->isn_package_id ?: (string) $localPkg->id,
                            'name' => $localPkg->name,
                            'active' => true,
                            'visible' => true,
                            'serviceIds' => $localPkg->isn_service_ids ?? [],
                        ];
                    }
                } else {
                    $localPkg = CatalogItem::query()
                        ->where('kind', CatalogKind::Package)
                        ->where(fn ($q) => $q->where('isn_package_id', $packageId)->orWhere('name', $package['name']))
                        ->where('status', PublicationStatus::Published)
                        ->first();
                    if ($localPkg) {
                        $package['active'] = true;
                        $package['visible'] = true;
                        if (! empty($localPkg->isn_service_ids)) {
                            $package['serviceIds'] = $localPkg->isn_service_ids;
                        }
                    }
                }

                abort_unless($package && $package['active'] && $package['visible'], 400, 'Select a visible package.');
                $bundled = $package['serviceIds'] ?? [];
            }
        }
        $selected = $services;
        $services = [];
        $addons = $this->content->publishedAddons(array_values(array_filter($selected, 'is_int')), $input['category']);
        foreach ($selected as $id) {
            if (is_int($id)) {
                $addon = $addons->get($id);
                abort_unless($addon, 400, 'Unknown add-on.');
                $local[] = $addon;
                $services = array_merge($services, $addon->isn_service_ids);
            } else {
                $services[] = $id;
            }
        }
        foreach ($services as $id) {
            $service = collect($catalog['services'])->firstWhere('id', $id);
            abort_unless($service && ($service['visible'] || in_array($id, $bundled, true)), 400, 'Select a visible service.');
        }
        $services = array_values(array_unique(array_merge($bundled, $services)));
        foreach ($services as $id) {
            abort_unless(collect($catalog['services'])->contains('id', $id), 400, 'The package references an unavailable service.');
        }
        abort_unless($services || $packageId, 400, 'Select at least one service or package.');
        $primary = collect($catalog['services'])->filter(fn ($row) => in_array($row['id'], $services, true))->sortBy('ancillary')->first(fn ($row) => ! empty($row['orderTypeId']));
        $orderType = $input['orderTypeId'] ?? $primary['orderTypeId'] ?? config('isn.order_types.'.$input['category']);
        $type = collect($catalog['orderTypes'])->firstWhere('id', $orderType);
        abort_unless($type && ($type['visible'] || ($packageId && $orderType === ($primary['orderTypeId'] ?? null))), 400, 'No verified ISN order type.');
        $p = $input['property'];
        $normalize = fn (string $v): string => preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($v));
        $aliases = ['SLAB' => 'SLABONGRADE', 'CRAWLSPACE' => 'CRAWLSPACEPIERANDBEAM', 'PIERBEAM' => 'CRAWLSPACEPIERANDBEAM'];
        $foundation = collect($catalog['foundationTypes'])->filter(fn ($f) => $f['visible'])->first(fn ($f) => $f['id'] === $p['foundationType'] || $normalize($f['name']) === $normalize($p['foundationType']) || $normalize($f['name']) === ($aliases[$normalize($p['foundationType'])] ?? $normalize($p['foundationType'])));
        abort_unless($foundation, 400, 'Select a supported foundation.');
        $p['foundationType'] = $foundation['id'];
        $p['foundationName'] = $foundation['name'];
        $checks = ['clientAttendance' => ['clientAttending', in_array($p['clientAttendance'] ?? '', ['FULL_INSPECTION', 'FINAL_WALKTHROUGH'], true)], 'occupancy' => ['occupied', ($p['occupancy'] ?? '') === 'OCCUPIED'], 'accessType' => ['gatedCommunity', ($p['accessType'] ?? '') !== 'OPEN'], 'alarmStatus' => ['alarmSystem', ($p['alarmStatus'] ?? '') !== 'NONE'], 'poolSpaType' => ['poolSpa', ($p['poolSpaType'] ?? '') !== 'NONE'], 'utilityStatus' => ['utilitiesOn', ($p['utilityStatus'] ?? '') === 'ALL_ON']];
        foreach ($checks as $key => [$boolean, $value]) {
            abort_if(isset($p[$key]) && $p[$boolean] !== $value, 400, $key.' does not match its summary boolean.');
        }
        $priced = $this->gateway->quote(self::propertyPayload($p), $services, $packageId, $orderType);
        foreach ($local as $addon) {
            $matched = false;
            foreach ($priced['lines'] as &$line) {
                if (in_array($line['id'], $addon->isn_service_ids, true)) {
                    $matched = true;
                    if ($line['amountCents'] === 0) {
                        $line['amountCents'] = $addon->price_cents;
                        $priced['totalCents'] += $addon->price_cents;
                    }
                }
            }
            unset($line);
            if (! $matched && ! $addon->isn_service_ids) {
                $priced['lines'][] = ['id' => $addon->id, 'name' => $addon->name, 'amountCents' => $addon->price_cents];
                $priced['totalCents'] += $addon->price_cents;
            }
        }

        return $priced + ['category' => $input['category'], 'property' => $p, 'serviceIds' => $services, 'packageId' => $packageId, 'orderTypeId' => $orderType, 'catalogIds' => array_map(fn ($a) => $a->id, $local), 'catalogVersions' => collect($local)->mapWithKeys(fn ($a) => [$a->id => $a->version])->all(), 'pricingSource' => 'ISN', 'expiresAt' => now()->addMinutes(30)->toIso8601String(), 'input' => $input];
    }
}
