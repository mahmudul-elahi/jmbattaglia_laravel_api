<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingDraft extends Model
{
    use HasFactory;

    protected $table = 'booking_drafts';

    protected $fillable = ['token_hash', 'data', 'current_step', 'version', 'expires_at', 'archival_quote_id', 'status', 'submission_key'];

    protected function casts(): array
    {
        return ['data' => 'array', 'expires_at' => 'immutable_datetime'];
    }
}
