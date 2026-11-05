<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PortalSettings extends Model
{
    use HasFactory;

    protected $table = 'portal_settings';

    protected $fillable = ['brand_name', 'help_phone', 'welcome_title', 'pricing_disclaimer', 'confirmation_message', 'version'];

    protected function casts(): array
    {
        return [];
    }

    public const CREATED_AT = null;
}
