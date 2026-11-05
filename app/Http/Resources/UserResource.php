<?php

namespace App\Http\Resources;

use App\Support\PortalSerializer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profile = $this->resource->relationLoaded('profile') ? $this->resource->getRelation('profile') : null;

        return ['fullName' => $this->name, 'firstName' => $profile?->first_name, 'lastName' => $profile?->last_name, 'avatarUrl' => $profile?->avatar_url, 'createdAt' => $this->created_at?->toIso8601String(), 'updatedAt' => $this->updated_at?->toIso8601String(), 'id' => (int) $this->id, 'email' => $this->email, 'name' => $this->name, 'isEmailVerified' => $this->email_verified_at !== null,
            'role' => $this->getRoleNames()->first(), 'roles' => $this->getRoleNames()->values()->all(),
            'permissions' => $this->getAllPermissions()->pluck('name')->sort()->values()->all(),
            'profile' => $this->whenLoaded('profile', fn () => $this->profile ? PortalSerializer::model($this->profile) : null)];
    }
}
