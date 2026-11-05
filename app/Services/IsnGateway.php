<?php

namespace App\Services;

use App\Exceptions\IsnGatewayException;
use App\Support\Money;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class IsnGateway implements IsnGatewayInterface
{
    public function configured(): bool
    {
        return config('isn.mode') !== 'local' && filled(config('isn.access_key')) && filled(config('isn.secret_key')) && filled(config('isn.base_url'));
    }

    private function call(string $path, string $method = 'GET', array $input = []): array
    {
        abort_unless($this->configured(), 503, 'ISN is disabled or not configured.');
        $url = rtrim(config('isn.base_url'), '/').$path;
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);
        abort_unless($scheme === 'https' || (app()->environment('testing') && in_array($host, ['127.0.0.1', 'localhost'])), 503, 'ISN requires HTTPS.');
        $safe = $method === 'GET' || $path === '/calculateservicequote';
        abort_if(! $safe && ! config('isn.writes_enabled'), 503, 'ISN writes are disabled.');
        $client = Http::withBasicAuth(config('isn.access_key'), config('isn.secret_key'))->acceptJson()->asJson()->timeout(config('isn.timeout'))->connectTimeout(5)->withoutRedirecting();
        $query = [];
        if (config('isn.office_id')) {
            $query['office'] = config('isn.office_id');
        }
        if ($method === 'GET') {
            $query += $input;
        }
        $encoded = http_build_query($query);
        $encoded = preg_replace('/services%5B[0-9]+%5D=/', 'services%5B%5D=', $encoded);
        $url .= $encoded ? '?'.$encoded : '';
        try {
            $response = $client->send($method, $url, $method === 'GET' ? [] : ['json' => $input]);
        } catch (ConnectionException $error) {
            throw new IsnGatewayException($safe ? 'UNAVAILABLE' : 'AMBIGUOUS');
        }
        if (! $response->successful()) {
            $remoteId = $response->json('id');
            throw new IsnGatewayException(match (true) {
                ! $safe && is_string($remoteId) && $remoteId !== '' => 'AMBIGUOUS',
                $response->status() === 403 => 'PERMISSION', $response->status() === 404 => 'NOT_FOUND', ! $safe && $response->status() >= 500 => 'AMBIGUOUS', $response->status() >= 500 || $response->status() === 429 => 'UNAVAILABLE', default => 'REJECTED'
            });
        }
        $body = $response->json();
        if (! is_array($body)) {
            throw new IsnGatewayException($safe ? 'UNAVAILABLE' : 'AMBIGUOUS');
        }
        if (($body['status'] ?? null) === 'error' && str_contains($path, '/lookup/') && str_contains(mb_strtolower($body['message'] ?? ''), 'could not locate')) {
            return $body;
        }
        if (($body['status'] ?? null) === 'partial' && $path === '/order' && ! empty($body['id'])) {
            return $body;
        }
        if (($body['status'] ?? null) !== 'ok') {
            throw new IsnGatewayException(! $safe && ! empty($body['id']) ? 'AMBIGUOUS' : (str_contains(mb_strtolower($body['message'] ?? ''), 'owner access level') ? 'PERMISSION' : 'REJECTED'), isset($body['id']) && is_string($body['id']) ? $body['id'] : null);
        }

        return $body;
    }

    private function cents(mixed $amount): int
    {
        try {
            return Money::cents($amount);
        } catch (\InvalidArgumentException) {
            throw new IsnGatewayException('UNAVAILABLE');
        }
    }

    private function rows(mixed $value): array
    {
        abort_unless(is_array($value), 503, 'ISN returned an invalid list.');

        return array_values($value);
    }

    private function id(array $row): string
    {
        $id = $row['id'] ?? $row['uuid'] ?? null;
        abort_unless(is_string($id) && $id !== '', 503, 'ISN identifier is missing.');

        return $id;
    }

    private function flag(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'yes', 'Yes'], true);
    }

    private function ids(array $rows): array
    {
        return array_map(fn ($row) => is_string($row) ? $row : $this->id($row), $rows);
    }

    private function packageRow(array $row): array
    {
        return ['id' => $this->id($row), 'name' => $row['name'] ?? '', 'description' => $row['description'] ?? '', 'active' => $this->flag($row['active'] ?? false), 'visible' => $this->flag($row['show'] ?? false), 'best' => $this->flag($row['best'] ?? false), 'sequence' => $row['sequence'] ?? 0, 'serviceIds' => $this->ids($row['services'] ?? []), 'modifierIds' => $this->ids($row['modifiers'] ?? []), 'source' => 'ISN'];
    }

    private function tenantKey(): string
    {
        return hash('sha256', config('isn.base_url').'|'.config('isn.office_id').'|'.config('isn.access_key'));
    }

    private function cacheKey(): string
    {
        return 'isn:catalog:'.$this->tenantKey().':'.Cache::get('isn:catalog-version:'.$this->tenantKey(), 1);
    }

    public function catalog(): array
    {
        abort_unless($this->configured(), 503, 'ISN is disabled or not configured.');

        return Cache::remember($this->cacheKey(), 60, function (): array {
            $services = $this->call('/services', 'GET', ['withAssociations' => 1]);
            $packages = $this->call('/packages', 'GET', ['withShow' => 1]);
            $types = $this->call('/ordertypes');
            $foundations = $this->call('/foundationtypes');

            return ['services' => array_map(fn (array $row): array => ['id' => $this->id($row), 'name' => $row['name'] ?? '', 'visible' => $this->flag($row['visible'] ?? $row['show'] ?? false), 'ancillary' => $this->flag($row['ancillary'] ?? false), 'orderTypeId' => $row['inspectiontypeid'] ?? null, 'priceCents' => isset($row['fee']) ? $this->cents($row['fee']) : null, 'source' => 'ISN'], $this->rows($services['services'] ?? null)),
                'packages' => array_map($this->packageRow(...), $this->rows($packages['packages'] ?? null)),
                'orderTypes' => array_map(fn (array $row): array => ['id' => $this->id($row), 'name' => $row['name'] ?? '', 'visible' => $this->flag($row['show'] ?? true)], $this->rows($types['ordertypes'] ?? null)),
                'foundationTypes' => array_map(fn (array $row): array => ['id' => $this->id($row), 'name' => $row['name'] ?? '', 'visible' => $this->flag($row['show'] ?? false)], $this->rows($foundations['foundationtypes'] ?? null))];
        });
    }

    public function capabilities(): array
    {
        $me = $this->call('/me')['me'] ?? [];
        $owner = $this->flag($me['owner'] ?? false);

        return ['principalId' => $this->id($me), 'owner' => $owner, 'canQuote' => $owner, 'canCreateAgent' => $owner, 'writesEnabled' => (bool) config('isn.writes_enabled')];
    }

    public function package(string $id): array
    {
        return $this->packageRow($this->call('/package/'.rawurlencode($id))['package'] ?? []);
    }

    public function writePackage(?string $id, array $input, bool $delete = false): array
    {
        try {
            $body = $this->call('/package'.($id ? '/'.rawurlencode($id) : ''), $delete ? 'DELETE' : 'POST', $input);
            if (empty($body['id']) && ! $id) {
                throw new IsnGatewayException('AMBIGUOUS');
            }

            return ['id' => $body['id'] ?? $id, 'source' => 'ISN'];
        } finally {
            Cache::forget($this->cacheKey());
            $versionKey = 'isn:catalog-version:'.$this->tenantKey();
            Cache::add($versionKey, 1, 86400);
            Cache::increment($versionKey);
        }
    }

    public function quote(array $property, array $services, ?string $packageId, string $orderTypeId): array
    {
        $body = $this->call('/calculateservicequote', 'POST', $property + ['services' => json_encode($services, JSON_THROW_ON_ERROR), 'ordertypeuuid' => $orderTypeId] + ($packageId ? ['package' => $packageId] : []));
        $rows = $this->rows($body['quotes'] ?? null);
        $lines = [];
        $bundle = [];
        if ($packageId) {
            foreach ($this->catalog()['packages'] as $package) {
                if ($package['id'] === $packageId) {
                    $bundle = $package['serviceIds'];
                }
            }
        }
        $hasPackage = count(array_filter($rows, fn ($row) => ! empty($row['package']))) > 0;
        foreach ($rows as $row) {
            $id = $row['package'] ?? $row['service'] ?? $row['id'] ?? $row['name'] ?? null;
            if (! $id && isset($row['fee'])) {
                continue;
            }
            if ($hasPackage && ! isset($row['package']) && in_array($id, $bundle, true)) {
                continue;
            }
            $amount = data_get($row, config('isn.quote_amount_field'));
            abort_unless(is_string($id) && is_numeric($amount) && is_finite((float) $amount) && abs((float) $amount) <= 1000000, 503, 'ISN returned an incomplete quote.');
            $lines[] = ['id' => $id, 'name' => $row['name'] ?? array_key_first($row['fees'] ?? []) ?? $id, 'amountCents' => $this->cents($amount)];
        }
        abort_if(! $lines, 503, 'ISN returned no quote lines.');

        return ['lines' => $lines, 'totalCents' => array_sum(array_column($lines, 'amountCents')), 'durationMinutes' => isset($body['duration']) && filter_var($body['duration'], FILTER_VALIDATE_INT) !== false && (int) $body['duration'] > 0 ? (int) $body['duration'] : null];
    }

    public function availability(string $zip, array $services, int $offset, int $days, ?string $inspectorId = null): array
    {
        $body = $this->call('/availableslots', 'GET', ['zip' => $zip, 'services' => $services, 'offset' => $offset, 'daysahead' => $days] + ($inspectorId ? ['inspector' => $inspectorId] : []));

        return array_map(function (array $row): array {
            abort_unless(isset($row['userid'], $row['start'], $row['end']), 503, 'Invalid ISN availability window.');

            return ['inspectorId' => $row['userid'], 'inspectorName' => $row['userdisplay'] ?? '', 'start' => $row['start'], 'end' => $row['end']];
        }, $this->rows($body['slots'] ?? null));
    }

    public function lookup(string $email, string $kind = 'client'): ?array
    {
        $body = $this->call('/'.$kind.'/lookup/'.rawurlencode(mb_strtolower(trim($email))));
        if (($body['status'] ?? null) === 'error') {
            return null;
        }
        $row = $body[$kind] ?? [];

        return ['id' => $this->id($row), 'firstName' => $row['first'] ?? $row['firstname'] ?? '', 'lastName' => $row['last'] ?? $row['lastname'] ?? '', 'email' => $row['email'] ?? $email, 'phone' => $row['mobilephone'] ?? $row['phone'] ?? $row['homephone'] ?? ''];
    }

    public function ensureContact(array $contact, string $kind = 'client'): array
    {
        $found = $this->lookup($contact['email'], $kind);
        if ($found) {
            return $found;
        }
        $body = $this->call('/'.$kind, 'POST', ['firstname' => $contact['firstName'], 'lastName' => $contact['lastName'], 'emailaddress' => $contact['email'], 'mobilephone' => $contact['phone']]);

        return ['id' => $this->id($body)] + $contact;
    }

    public function createOrder(array $payload): array
    {
        if (config('isn.office_id')) {
            $payload['officeuuid'] = config('isn.office_id');
        }
        $body = $this->call('/order', 'POST', $payload);
        if (empty($body['id']) || ! is_string($body['id'])) {
            throw new IsnGatewayException('AMBIGUOUS');
        }

        return ['id' => $this->id($body), 'orderNumber' => $body['oid'] ?? null, 'partial' => ($body['status'] ?? null) === 'partial'];
    }

    public function readOrder(string $id): array
    {
        $row = $this->call('/order/'.rawurlencode($id))['order'] ?? [];
        $notes = $row['notes'] ?? $this->call('/order/notes/'.rawurlencode($id))['notes'] ?? [];
        $row['portalNotes'] = array_column($notes, 'text');
        unset($row['notes']);

        return $row;
    }
}
