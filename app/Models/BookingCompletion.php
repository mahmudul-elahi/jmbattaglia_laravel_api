<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingCompletion extends Model
{
    use HasFactory;

    protected $table = 'booking_completions';

    protected $fillable = ['booking_id', 'reference', 'idempotency_key', 'payload', 'estimated_total_cents', 'status', 'remote_submission_key', 'archival_integration_quote_id', 'sync_input', 'lease_id', 'lease_expires_at', 'error_code'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'sync_input' => 'array', 'lease_expires_at' => 'immutable_datetime'];
    }
}
