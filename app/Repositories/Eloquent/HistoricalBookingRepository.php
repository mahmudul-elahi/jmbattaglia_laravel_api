<?php

namespace App\Repositories\Eloquent;

use App\Models\BookingCompletion;
use App\Support\PortalSerializer;

final class HistoricalBookingRepository
{
    public function listing(int $page, int $limit): array
    {
        $rows = BookingCompletion::query()->orderByDesc('id')->paginate($limit, ['*'], 'page', $page);

        return ['items' => $rows->getCollection()->map(fn ($row) => PortalSerializer::model($row)), 'total' => $rows->total(), 'page' => $rows->currentPage(), 'limit' => $rows->perPage()];
    }

    public function findByBooking(int $bookingId): BookingCompletion
    {
        return BookingCompletion::query()->where('booking_id', $bookingId)->firstOrFail();
    }

    public function claim(int $id, string $lease): bool
    {
        return BookingCompletion::query()->whereKey($id)->whereIn('status', ['PENDING_CONFIGURATION', 'FAILED', 'PROCESSING'])->where(fn ($q) => $q->whereNull('lease_expires_at')->orWhere('lease_expires_at', '<', now()))->update(['status' => 'PROCESSING', 'lease_id' => $lease, 'lease_expires_at' => now()->addMinutes(5)]) === 1;
    }

    public function update(BookingCompletion $record, array $input): void
    {
        $record->update($input);
    }

    public function release(int $id, string $lease): void
    {
        BookingCompletion::query()->whereKey($id)->where('lease_id', $lease)->update(['lease_id' => null, 'lease_expires_at' => null]);
    }
}
