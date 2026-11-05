<?php

namespace App\Services;

use App\Exceptions\IsnGatewayException;
use App\Jobs\SendBookingReceipt;
use App\Models\OrderSubmission;
use App\Models\QuoteSnapshot;
use App\Support\CanonicalJson;
use App\Support\Money;
use App\Support\PortalSerializer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class OrderingService implements OrderingServiceInterface
{
    public function __construct(
        private QuoteService $quotes,
        private AvailabilityService $availability,
        private IsnGatewayInterface $gateway
    ) {}

    public function listing(array $filters): array
    {
        $rows = OrderSubmission::query()
            ->when(isset($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->orderByDesc('id')
            ->paginate($filters['limit'] ?? 20, ['*'], 'page', $filters['page'] ?? 1);

        return [
            'items' => $rows->getCollection()->map(fn ($row) => PortalSerializer::model($row)),
            'total' => $rows->total(),
            'page' => $rows->currentPage(),
            'limit' => $rows->perPage(),
        ];
    }

    public function inspect(int $id): OrderSubmission
    {
        return OrderSubmission::query()->with('quote')->findOrFail($id);
    }

    public function findByKey(string $key): ?OrderSubmission
    {
        return OrderSubmission::query()->with('quote')->where('idempotency_key', $key)->first();
    }

    public function reserve(string $key, string $hash, array $input, array $preview): OrderSubmission
    {
        try {
            return DB::transaction(function () use ($key, $hash, $input, $preview): OrderSubmission {
                $attributes = PortalSerializer::attributes(Arr::except($preview, ['input']));
                $attributes['preview_token'] = $input['quoteId'];
                $quote = QuoteSnapshot::query()->create($attributes);
                $order = OrderSubmission::query()->create(['idempotency_key' => $key, 'request_hash' => $hash, 'quote_id' => $quote->id, 'request_data' => $input]);
                $order->update(['reference' => 'AE-'.$order->id]);

                return $order->load('quote');
            });
        } catch (UniqueConstraintViolationException $error) {
            $existing = $this->findByKey($key);
            if ($existing) {
                if ($existing->status?->value === 'FAILED') {
                    $existing->delete();

                    return $this->reserve($key, $hash, $input, $preview);
                }
                abort_unless(hash_equals($existing->request_hash, $hash), 409, 'Idempotency key was used for a different request.');

                return $existing;
            }
            abort(409, 'This quote has already been submitted.');
        }
    }

    public function startWrite(int $id): bool
    {
        return OrderSubmission::query()->whereKey($id)->where('status', 'PROCESSING')->where('phase', 'CONTACTS_STARTED')->update(['phase' => 'ORDER_WRITE_STARTED']) === 1;
    }

    public function claim(int $id): bool
    {
        return OrderSubmission::query()->whereKey($id)->where('status', 'PROCESSING')->where('phase', 'PREPARING')->update(['phase' => 'CONTACTS_STARTED']) === 1;
    }

    public function update(int $id, array $attributes): OrderSubmission
    {
        $order = OrderSubmission::query()->findOrFail($id);
        $order->update($attributes);

        return $order->load('quote');
    }

    public function complete(int $id, ?int $actor = null): OrderSubmission
    {
        $order = DB::transaction(function () use ($id): OrderSubmission {
            $order = OrderSubmission::query()->lockForUpdate()->findOrFail($id);
            abort_unless(in_array($order->status?->value, ['PROCESSING', 'REQUIRES_REVIEW', 'FAILED', 'SUBMITTED'], true), 409, 'Invalid submission transition.');
            if ($order->status?->value !== 'SUBMITTED') {
                $order->update(['status' => 'SUBMITTED', 'phase' => 'ORDER_VERIFIED', 'error_code' => null, 'notification_pending' => true]);
            }

            return $order->load('quote');
        });

        if (! config('portal.mail_disabled')) {
            SendBookingReceipt::dispatch($order->id)->afterCommit()->onQueue('notifications');
        }

        return $order;

        if (! config('portal.mail_disabled')) {
            SendBookingReceipt::dispatch($order->id)->afterCommit()->onQueue('notifications');
        }

        return $order;
    }

    public function submit(string $key, array $input): OrderSubmission
    {
        $hash = CanonicalJson::hash($input);
        $existing = $this->findByKey($key);
        if ($existing) {
            if ($existing->status?->value === 'FAILED') {
                $existing->delete();
                $existing = null;
            } else {
                abort_unless($existing->hash_version === 'php-canonical-v1', 409, 'Imported requests are available for receipt and reconciliation only.');
                abort_unless(hash_equals($existing->request_hash, $hash), 409, 'Idempotency key was used for a different request.');

                return $existing;
            }
        }
        $preview = $this->quotes->find($input['quoteId']);
        $norm = function (array $t): array {
            $t['serviceIds'] = array_values(array_unique($t['serviceIds'] ?? []));
            if (in_array($t['packageId'] ?? null, ['d0d3f4f4-d184-4168-9e87-725f37bda9e9', 'residential-home', 'bbbc1155-310f-11e3-80f0-22000afd1674'], true)) {
                $t['packageId'] = null;
                $t['serviceIds'] = array_values(array_unique(array_merge($t['serviceIds'], ['bbbc1155-310f-11e3-80f0-22000afd1674'])));
            }

            return $t;
        };
        $cleanInput = $norm($input);
        abort_unless($cleanInput['category'] === $preview['category'], 409, 'Submitted category does not match the quote.');
        abort_unless(($cleanInput['packageId'] ?? null) === ($preview['packageId'] ?? null), 409, 'Submitted package does not match the quote.');
        if (! empty($cleanInput['packageId'])) {
            abort_unless(empty(array_diff($cleanInput['serviceIds'], $preview['serviceIds'])), 409, 'Submitted services do not match the quote.');
        } else {
            abort_unless(empty(array_diff($cleanInput['serviceIds'], $preview['serviceIds'])) && empty(array_diff($preview['serviceIds'], $cleanInput['serviceIds'])), 409, 'Submitted services do not match the quote.');
        }

        $quoteInput = [
            'category' => $cleanInput['category'],
            'property' => $input['property'],
            'serviceIds' => $cleanInput['serviceIds'],
            'packageId' => $cleanInput['packageId'] ?? null,
            'orderTypeId' => $cleanInput['orderTypeId'] ?? $preview['orderTypeId'],
        ];
        $order = $this->reserve($key, $hash, $input, $preview);
        if (! $this->claim($order->id)) {
            return $order->refresh()->load('quote');
        }
        $writeStarted = false;
        try {
            $current = $this->quotes->price($quoteInput);
            abort_unless($current['totalCents'] === $preview['totalCents'] && CanonicalJson::hash($current['lines']) === CanonicalJson::hash($preview['lines']) && $current['durationMinutes'] === $preview['durationMinutes'] && $current['serviceIds'] === $preview['serviceIds'] && $current['orderTypeId'] === $preview['orderTypeId'], 409, 'The quote has changed.');
            $this->availability->assertSelected($preview, $input);
            $capabilities = $this->gateway->capabilities();
            abort_unless($capabilities['writesEnabled'] && $capabilities['canQuote'] && ($input['customerType'] !== 'BUYERS_AGENT' || $capabilities['canCreateAgent']), 503, 'ISN booking is not enabled.');
            $buyer = $input['customerType'] === 'BUYERS_AGENT' ? $input['buyerContact'] : $input['contact'];
            $client = $this->gateway->ensureContact($buyer);
            $order = $this->update($order->id, ['client_id' => $client['id']]);
            $agent = $input['customerType'] === 'BUYERS_AGENT' ? $this->gateway->ensureContact($input['contact'], 'agent') : null;
            $order = $this->update($order->id, ['agent_id' => $agent['id'] ?? null]);
            abort_unless($this->startWrite($order->id), 409, 'Submission is no longer available for remote write.');
            $writeStarted = true;
            $order = $this->inspect($order->id);
            $p = $preview['property'];
            $payload = QuoteService::propertyPayload($p, true) + ['clientuuid' => $client['id'], 'ordertypeuuid' => $preview['orderTypeId'], 'services' => $preview['serviceIds'], 'datetime' => $input['date'].' '.$input['time'].':00', 'duration' => $preview['durationMinutes'], 'inspector1uuid' => $input['inspectorId'], 'inprogress' => true, 'clientattending' => $p['clientAttending'] ? 'Yes' : 'No', 'propertyoccupied' => $p['occupied'], 'utilitieson' => $p['utilitiesOn'], 'gatecode' => $p['gateCode'] ?? '', 'referredreason' => $p['referral'] ?? 'Website', 'notes' => [['owner' => $capabilities['principalId'], 'text' => $this->note($order)]]];
            if ($agent) {
                $payload['buyersagentuuid'] = $agent['id'];
            }
            if ($preview['packageId']) {
                $payload['packages'] = [['package' => $preview['packageId']]];
            }
            $remote = $this->gateway->createOrder($payload);
            $order = $this->update($order->id, ['remote_order_id' => $remote['id'], 'remote_order_number' => $remote['orderNumber'], 'phase' => 'ORDER_SAVED']);
            if ($remote['partial']) {
                return $this->update($order->id, ['status' => 'REQUIRES_REVIEW', 'error_code' => 'ISN_PARTIAL_ORDER']);
            }
            $saved = $this->gateway->readOrder($remote['id']);
            if (! $this->verify($order, $saved)) {
                return $this->update($order->id, ['status' => 'REQUIRES_REVIEW', 'error_code' => 'ISN_READBACK_MISMATCH']);
            }

            return $this->complete($order->id);
        } catch (\Throwable $error) {
            $review = $writeStarted || ($error instanceof IsnGatewayException && $error->kind === 'AMBIGUOUS');
            $attributes = ['status' => $review ? 'REQUIRES_REVIEW' : 'FAILED', 'error_code' => $review ? 'ISN_UNCERTAIN_WRITE' : 'PREPARATION_FAILED'];
            if ($writeStarted && $error instanceof IsnGatewayException && $error->remoteId) {
                $attributes['remote_order_id'] = $error->remoteId;
            }
            $order = $this->update($order->id, $attributes);
            if ($review) {
                return $order;
            }
            throw $error;
        }
    }

    public function receipt(int $id, string $key): OrderSubmission
    {
        abort_unless(preg_match('/^(?:[a-f0-9]{64}|[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12})$/i', $key), 404);

        return OrderSubmission::query()->with('quote')->whereKey($id)->where('idempotency_key', $key)->firstOrFail();
    }

    public function note(OrderSubmission $order): string
    {
        $input = $order->request_data;
        $p = $order->quote->property;
        $contact = fn (array $c): string => $c['firstName'].' '.$c['lastName'].'; '.$c['email'].'; '.$c['phone'];
        $lines = ['Portal request '.$order->reference, 'Customer type: '.$input['customerType'], 'Submitted contact: '.$contact($input['contact'])];
        if (isset($input['buyerContact'])) {
            $lines[] = 'Buyer contact: '.$contact($input['buyerContact']);
        }
        $boolean = fn (bool $value): string => $value ? 'true' : 'false';
        $lines = array_merge($lines, [
            'Foundation: '.($p['foundationDescription'] ?? $p['foundationType']),
            'Client attendance: '.($p['clientAttendance'] ?? $boolean($p['clientAttending'])),
            'Occupancy: '.($p['occupancy'] ?? $boolean($p['occupied'])),
            'Property access: '.($p['accessType'] ?? $boolean($p['gatedCommunity'])),
            'Alarm: '.($p['alarmStatus'] ?? $boolean($p['alarmSystem'])),
            'Pool/spa: '.($p['poolSpaType'] ?? $boolean($p['poolSpa'])),
            'Utilities: '.($p['utilityStatus'] ?? $boolean($p['utilitiesOn'])),
            'Sewage: '.($p['sewageSystem'] ?? 'Public'),
            'Water: '.($p['waterSystem'] ?? 'Public'),
            'Gate code: '.($p['gateCode'] ?? 'Not provided'),
            'Referral: '.($p['referral'] ?? 'Website'),
            'Due diligence: '.($p['dueDiligence'] ?? 'Not provided'),
            'Due diligence date: '.($p['dueDiligenceDate'] ?? 'Not provided'),
        ]);
        $lines[] = $p['notes'] ?? '';

        return implode("\n", $lines);
    }

    public function verify(OrderSubmission $order, array $saved): bool
    {
        $property = $order->quote->property;
        $input = $order->request_data;
        if (! is_string($saved['datetime'] ?? null) || (isset($saved['address2']) && ! is_string($saved['address2']))) {
            return false;
        }
        try {
            $fee = Money::cents($saved['totalfee'] ?? null);
        } catch (\InvalidArgumentException) {
            return false;
        }
        $date = null;
        foreach (['!Y-m-d H:i:s', '!Y-m-d H:i', '!Y-m-d\\TH:i:s', '!Y-m-d\\TH:i', '!n/j/Y H:i'] as $format) {
            $candidate = \DateTimeImmutable::createFromFormat($format, $saved['datetime'], new \DateTimeZone(config('isn.timezone')));
            $errors = \DateTimeImmutable::getLastErrors();
            if ($candidate && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                $date = $candidate->format('Y-m-d H:i');
                break;
            }
        }
        $services = $saved['services'] ?? [];
        if (is_string($services)) {
            $services = json_decode($services, true);
        }
        if (! is_array($services)) {
            return false;
        }
        $services = array_map(fn ($row) => is_string($row) ? $row : (is_array($row) ? ($row['uuid'] ?? $row['serviceid'] ?? $row['serviceuuid'] ?? $row['service'] ?? $row['id'] ?? '') : ''), $services);
        $expectedServices = $order->quote->service_ids;
        sort($expectedServices);
        sort($services);
        $normalize = fn (string $text): string => trim(preg_replace('/\s+/', ' ', $text));
        $notesMatch = collect($saved['portalNotes'] ?? [])->contains(fn ($text) => is_string($text) && str_contains($normalize($text), $normalize($this->note($order))));
        $foundation = $saved['foundationtypeuuid'] ?? $saved['foundationtypeid'] ?? $saved['foundation'] ?? $saved['foundationtype'] ?? null;
        $addressMatch = ($saved['address1'] ?? null) === trim($property['address1']) && trim($saved['address2'] ?? '') === trim($property['address2'] ?? '') && ($saved['city'] ?? null) === trim($property['city']) && ($saved['zip'] ?? null) === $property['zip'] && ($saved['stateabbreviation'] ?? $saved['state'] ?? null) === $property['state'];
        $propertyMatch = (int) ($saved['squarefeet'] ?? 0) === $property['squareFeet'] && (int) ($saved['yearbuilt'] ?? 0) === $property['yearBuilt'] && in_array($foundation, array_filter([$property['foundationType'], $property['foundationName'] ?? null]), true);
        $contactMatch = $order->client_id && ($saved['client'] ?? null) === $order->client_id && ($input['customerType'] !== 'BUYERS_AGENT' || ($order->agent_id && ($saved['buyersagent'] ?? null) === $order->agent_id));
        $selectionMatch = $services === $expectedServices && ($saved['ordertype'] ?? null) === $order->quote->order_type_id;
        $appointmentMatch = ($saved['inspector1'] ?? null) === $input['inspectorId'] && $date === $input['date'].' '.$input['time'] && isset($saved['duration']) && (int) $saved['duration'] === $order->quote->duration_minutes;

        return $notesMatch && ($saved['id'] ?? null) === $order->remote_order_id && $addressMatch && $propertyMatch && $contactMatch && $selectionMatch && $appointmentMatch && $fee === $order->quote->total_cents;
    }

    public function reconcile(int $id, string $remoteId, ?int $actor = null): OrderSubmission
    {
        $order = $this->inspect($id);
        abort_unless(in_array($order->status?->value, ['REQUIRES_REVIEW', 'FAILED'], true), 409, 'Only failed or review submissions may be reconciled.');
        abort_if($order->remote_order_id && $order->remote_order_id !== $remoteId, 409, 'Remote order identifier does not match.');
        $saved = $this->gateway->readOrder($remoteId);
        $order->remote_order_id = $remoteId;
        abort_unless($this->verify($order, $saved), 409, 'Remote order does not match this submission.');
        $this->update($id, ['remote_order_id' => $remoteId]);

        return $this->complete($id, $actor);
    }
}
