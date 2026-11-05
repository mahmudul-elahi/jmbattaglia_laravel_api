<?php

namespace App\Models;

use App\Enums\MediaStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MediaAsset extends Model
{
    use HasFactory;

    protected $table = 'media_assets';

    protected $fillable = ['object_key', 'filename', 'mime_type', 'size_bytes', 'checksum', 'url', 'alt_text', 'status', 'created_by', 'actor_label', 'expires_at'];

    protected function casts(): array
    {
        return ['status' => MediaStatus::class, 'expires_at' => 'immutable_datetime', 'size_bytes' => 'integer'];
    }
}
