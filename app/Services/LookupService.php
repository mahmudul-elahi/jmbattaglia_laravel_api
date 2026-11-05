<?php

namespace App\Services;

use App\Jobs\SendSecurityCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

final class LookupService
{
    public function __construct(private IsnGatewayInterface $gateway) {}

    public function exists(string $email): array
    {
        return ['found' => $this->gateway->lookup($email) !== null];
    }

    public function start(array $input): array
    {
        $challenge = bin2hex(random_bytes(32));
        $code = (string) random_int(100000, 999999);
        $store = Cache::store(config('portal.preview_store'));
        $store->put('portal:lookup:'.$challenge, ['email' => $input['email'], 'kind' => $input['customerType'] === 'BUYERS_AGENT' ? 'agent' : 'client', 'hash' => Hash::make($code), 'attempts' => 0, 'expires' => now()->addMinutes(10)->timestamp], 600);
        if (! config('portal.mail_disabled')) {
            SendSecurityCode::dispatch($input['email'], $code, 'LOOKUP')->onQueue('mail');
        }

        return ['challengeId' => $challenge, 'expiresIn' => 600];
    }

    public function verify(string $challenge, string $code): array
    {
        $store = Cache::store(config('portal.preview_store'));
        $key = 'portal:lookup:'.$challenge;
        $result = $store->lock($key.':lock', 10)->block(3, function () use ($store, $key, $code): ?array {
            $record = $store->get($key);
            if (! $record || $record['attempts'] >= 5 || $record['expires'] <= now()->timestamp) {
                return null;
            }
            $record['attempts']++;
            $store->put($key, $record, max(1, $record['expires'] - now()->timestamp));
            if (! Hash::check($code, $record['hash'])) {
                return null;
            }
            $store->forget($key);

            return $record;
        });
        abort_unless($result, 400, 'Invalid or expired lookup code.');
        $contact = $this->gateway->lookup($result['email'], $result['kind']);

        return ['found' => $contact !== null, 'contact' => $contact];
    }

    public function createClient(array $input): array
    {
        return $this->gateway->ensureContact($input);
    }
}
