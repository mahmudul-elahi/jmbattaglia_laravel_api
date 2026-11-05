<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\OrderingRepositoryInterface;
use App\Models\AuditLog;
use App\Models\OrderSubmission;
use App\Models\QuoteSnapshot;
use App\Support\PortalSerializer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class OrderingRepository implements OrderingRepositoryInterface
{
    public function listing(array $filters): array
    {
        $rows = OrderSubmission::query()->when(isset($filters['status']), fn ($q) => $q->where('status', $filters['status']))->orderByDesc('id')->paginate($filters['limit'] ?? 20, ['*'], 'page', $filters['page'] ?? 1);

        return ['items' => $rows->getCollection()->map(fn ($row) => PortalSerializer::model($row)), 'total' => $rows->total(), 'page' => $rows->currentPage(), 'limit' => $rows->perPage()];
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
        return DB::transaction(function () use ($id, $actor): OrderSubmission {
            $order = OrderSubmission::query()->lockForUpdate()->findOrFail($id);
            abort_unless(in_array($order->status?->value, ['PROCESSING', 'REQUIRES_REVIEW', 'FAILED', 'SUBMITTED'], true), 409, 'Invalid submission transition.');
            if ($order->status?->value !== 'SUBMITTED') {
                $order->update(['status' => 'SUBMITTED', 'phase' => 'ORDER_VERIFIED', 'error_code' => null, 'notification_pending' => true]);
            }
            if ($actor) {
                AuditLog::query()->create(['actor_id' => $actor, 'action' => 'RECONCILE', 'entity_type' => 'submissions', 'local_entity_id' => $id, 'external_entity_id' => $order->remote_order_id]);
            }

            return $order->load('quote');
        });
    }

    public function receipt(int $id, string $key): OrderSubmission
    {
        return OrderSubmission::query()->with('quote')->whereKey($id)->where('idempotency_key',$key)->firstOrFail();
    }
}
