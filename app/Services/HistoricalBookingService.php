<?php

namespace App\Services;

use App\Models\BookingCompletion;
use App\Models\CatalogItem;
use App\Support\PortalSerializer;
use Illuminate\Support\Arr;

final class HistoricalBookingService
{
    public function __construct(
        private QuoteService $quotes,
        private AvailabilityService $availability,
        private OrderingService $ordering,
        private IsnGatewayInterface $gateway
    ) {}

    public function listing(int $page, int $limit): array
    {
        $rows = BookingCompletion::query()->orderByDesc('id')->paginate($limit, ['*'], 'page', $page);

        return [
            'items' => $rows->getCollection()->map(fn ($row) => PortalSerializer::model($row)),
            'total' => $rows->total(),
            'page' => $rows->currentPage(),
            'limit' => $rows->perPage(),
        ];
    }

    public function sync(int $bookingId): BookingCompletion
    {
        $record = BookingCompletion::query()->where('booking_id', $bookingId)->firstOrFail();
        abort_unless($this->gateway->configured() && config('isn.writes_enabled'), 503, 'ISN synchronization is disabled.');
        if (in_array($record->status, ['SUBMITTED', 'REQUIRES_REVIEW'], true)) {
            return $record;
        }
        $lease = bin2hex(random_bytes(32));
        abort_unless($this->claim($record->id, $lease), 409, 'Historical synchronization is already running.');
        try {
            $key = hash('sha256', 'historical:'.$record->remote_submission_key);
            $existing = $this->ordering->findByKey($key);
            if ($existing) {
                $record->update(['status' => $existing->status?->value === 'PROCESSING' ? 'REQUIRES_REVIEW' : $existing->status?->value, 'error_code' => $existing->error_code]);

                return $record;
            }
            if (! $record->sync_input) {
                $payload = $record->payload;
                $quoteInput = Arr::only($payload['quote'], ['category', 'property', 'serviceIds', 'packageId', 'orderTypeId']);
                if (isset($quoteInput['packageId']) && is_int($quoteInput['packageId'])) {
                    $package = CatalogItem::query()->findOrFail($quoteInput['packageId']);
                    $quoteInput['packageId'] = $package->isn_package_id;
                    $quoteInput['serviceIds'] = $package->isn_service_ids;
                    $quoteInput['orderTypeId'] = $package->isn_order_type_id;
                }
                $quoteInput['serviceIds'] = array_values(array_merge($quoteInput['serviceIds'] ?? [], $payload['quote']['addOnIds'] ?? []));
                $quoteInput = array_filter($quoteInput, fn ($value) => $value !== null);
                $quoteInput['property']['notes'] = trim(($quoteInput['property']['notes'] ?? '')."\nLocal booking reference: ".$record->reference);
                $priced = $this->quotes->create($quoteInput);
                $preview = $this->quotes->find($priced['quoteId']);
                $appointment = $payload['appointment'];
                $slots = $this->availability->starts($preview, $appointment['date'], 1);
                $slot = collect($slots)->first(fn ($slot) => $slot['time'] === $appointment['time'] && (! isset($appointment['inspectorId']) || $slot['inspectorId'] === $appointment['inspectorId']));
                abort_unless($slot, 409, 'Historical appointment requires staff review.');
                $input = $quoteInput + Arr::only($payload, ['customerType', 'contact', 'buyerContact']) + ['quoteId' => $priced['quoteId'], 'date' => $appointment['date'], 'time' => $appointment['time'], 'inspectorId' => $slot['inspectorId']];
                $record->update(['sync_input' => $input]);
            }
            $order = $this->ordering->submit($key, $record->sync_input);
            $record->update(['status' => $order->status?->value, 'error_code' => $order->error_code]);

            return $record;
        } catch (\Throwable $error) {
            $record->update(['status' => 'REQUIRES_REVIEW', 'error_code' => 'HISTORICAL_SYNC_REVIEW']);
            throw $error;
        } finally {
            $this->release($record->id, $lease);
        }
    }

    private function claim(int $id, string $lease): bool
    {
        return BookingCompletion::query()
            ->whereKey($id)
            ->whereIn('status', ['PENDING_CONFIGURATION', 'FAILED', 'PROCESSING'])
            ->where(fn ($q) => $q->whereNull('lease_expires_at')->orWhere('lease_expires_at', '<', now()))
            ->update(['status' => 'PROCESSING', 'lease_id' => $lease, 'lease_expires_at' => now()->addMinutes(5)]) === 1;
    }

    private function release(int $id, string $lease): void
    {
        BookingCompletion::query()->whereKey($id)->where('lease_id', $lease)->update(['lease_id' => null, 'lease_expires_at' => null]);
    }
}
