<?php

namespace App\Services;

use App\Contracts\Gateways\IsnGatewayInterface;
use App\Contracts\Repositories\OrderingRepositoryInterface;
use App\Models\BookingCompletion;
use App\Models\CatalogItem;
use App\Repositories\Eloquent\HistoricalBookingRepository;
use Illuminate\Support\Arr;

final class HistoricalBookingService
{
    public function __construct(private QuoteService $quotes, private AvailabilityService $availability, private OrderingService $ordering, private OrderingRepositoryInterface $orders, private IsnGatewayInterface $gateway, private HistoricalBookingRepository $repository) {}

    public function sync(int $bookingId): BookingCompletion
    {
        $record = $this->repository->findByBooking($bookingId);
        abort_unless($this->gateway->configured() && config('isn.writes_enabled'), 503, 'ISN synchronization is disabled.');
        if (in_array($record->status, ['SUBMITTED', 'REQUIRES_REVIEW'], true)) {
            return $record;
        }
        $lease = bin2hex(random_bytes(32));
        abort_unless($this->repository->claim($record->id, $lease), 409, 'Historical synchronization is already running.');
        try {
            $key = hash('sha256', 'historical:'.$record->remote_submission_key);
            $existing = $this->orders->findByKey($key);
            if ($existing) {
                $this->repository->update($record, ['status' => $existing->status?->value === 'PROCESSING' ? 'REQUIRES_REVIEW' : $existing->status?->value, 'error_code' => $existing->error_code]);

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
                $this->repository->update($record, ['sync_input' => $input]);
            }
            $order = $this->ordering->submit($key, $record->sync_input);
            $this->repository->update($record, ['status' => $order->status?->value, 'error_code' => $order->error_code]);

            return $record;
        } catch (\Throwable $error) {
            $this->repository->update($record, ['status' => 'REQUIRES_REVIEW', 'error_code' => 'HISTORICAL_SYNC_REVIEW']);
            throw $error;
        } finally {
            $this->repository->release($record->id,$lease);
        }
    }
}
