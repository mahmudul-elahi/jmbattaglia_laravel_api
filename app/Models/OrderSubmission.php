<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderSubmission extends Model
{
    use HasFactory;

    protected $table = 'order_submissions';

    protected $fillable = ['reference', 'idempotency_key', 'request_hash', 'hash_version', 'quote_id', 'request_data', 'status', 'phase', 'client_id', 'agent_id', 'remote_order_id', 'remote_order_number', 'error_code', 'notification_pending', 'notification_sent_at'];

    protected function casts(): array
    {
        return ['quote_id' => 'integer', 'remote_order_number' => 'integer', 'status' => SubmissionStatus::class, 'request_data' => 'array', 'notification_pending' => 'boolean', 'notification_sent_at' => 'immutable_datetime'];
    }

    protected $hidden = ['idempotency_key', 'request_hash'];

    public function quote(): BelongsTo
    {
        return $this->belongsTo(QuoteSnapshot::class, 'quote_id');
    }
}
