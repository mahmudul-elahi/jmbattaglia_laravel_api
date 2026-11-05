<?php

namespace App\Services;

use App\Contracts\Gateways\IsnGatewayInterface;
use App\Contracts\Repositories\QuotePreviewRepositoryInterface;
use App\Repositories\Eloquent\ContentRepository;
use Illuminate\Support\Arr;

final class QuoteService
{
    public function __construct(private IsnGatewayInterface $gateway, private QuotePreviewRepositoryInterface $previews, private ContentRepository $content) {}

    public static function propertyPayload(array $p, bool $order = false): array
    {
        return ['address1' => trim($p['address1']), 'address2' => trim($p['address2'] ?? ''), 'city' => trim($p['city']), 'state' => $p['state'], 'zip' => $p['zip'], 'squarefeet' => (string) $p['squareFeet'], 'yearbuilt' => (string) $p['yearBuilt'], ($order ? 'foundationtypeuuid' : 'foundationtype') => $p['foundationType']];
    }

    public function create(array $input): array
    {
        $preview = $this->price($input);
        $token = $this->previews->put($preview);

        return ['quoteId' => $token] + Arr::only($preview, ['lines', 'totalCents', 'durationMinutes', 'serviceIds', 'packageId', 'orderTypeId', 'expiresAt']) + ['currency' => 'USD', 'timezone' => config('isn.timezone'), 'pricingStatus' => 'ESTIMATE'];
    }

    public function find(string $token): array
    {
        $quote = $this->previews->find($token);
        abort_unless($quote, 409, 'Quote has expired. Request a new quote.');

        return $quote;
    }

    public function price(array $input): array
    {
        $catalog = $this->gateway->catalog();
        $services = $input['serviceIds'] ?? [];
        $packageId = $input['packageId'] ?? null;
        $bundled = [];
        $local = [];
        if ($packageId) {
            $package = collect($catalog['packages'])->firstWhere('id', $packageId);
            abort_unless($package && $package['active'] && $package['visible'], 400, 'Select a visible package.');
            $bundled = $package['serviceIds'];
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
        foreach ($checks as $key => [$boolean,$value]) {
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
