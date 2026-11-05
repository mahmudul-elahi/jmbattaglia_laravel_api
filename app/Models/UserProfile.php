<?php

namespace App\Models;

use App\Enums\VisaStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    use HasFactory;

    protected $table = 'user_profiles';

    protected $fillable = ['user_id', 'first_name', 'last_name', 'headline', 'about', 'country_code', 'visa_status', 'avatar_url'];

    protected function casts(): array
    {
        return ['user_id' => 'integer', 'visa_status' => VisaStatus::class];
    }
}
