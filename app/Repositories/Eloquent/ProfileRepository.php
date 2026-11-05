<?php

namespace App\Repositories\Eloquent;

use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProfileRepository
{
    public function load(User $user): User
    {
        return $user->load('profile');
    }

    public function update(User $user, array $input, bool $sensitive, ?MediaAsset $uploaded): User
    {
        return DB::transaction(function () use ($user, $input, $sensitive, $uploaded): User {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_unless($locked->password === $user->password && $locked->email === $user->email, 409, 'Account credentials have changed.');
            if (isset($input['email'])) {
                abort_if(User::query()->where('email', $input['email'])->whereKeyNot($user->id)->exists(), 409, 'Email is already registered.');
                $locked->email = $input['email'];
            }
            if (isset($input['newPassword'])) {
                $locked->password = $input['newPassword'];
            }
            $profile = $locked->profile()->firstOrCreate([]);
            $attributes = [];
            if (isset($input['name'])) {
                $locked->name = $input['name'];
                $attributes += ['first_name' => $input['name'], 'last_name' => ''];
            } else {
                foreach (['firstName', 'lastName'] as $key) {
                    if (array_key_exists($key, $input)) {
                        $attributes[Str::snake($key)] = $input[$key];
                    }
                }
            }
            if (array_key_exists('avatarMediaId', $input)) {
                $asset = $input['avatarMediaId'] ? MediaAsset::query()->whereKey($input['avatarMediaId'])->where('status', 'READY')->whereIn('mime_type', ['image/jpeg', 'image/png'])->lockForUpdate()->firstOrFail() : null;
                $attributes['avatar_url'] = $asset?->url;
            }
            if ($uploaded) {
                $ready = MediaAsset::query()->whereKey($uploaded->id)->where('status', 'READY')->lockForUpdate()->firstOrFail();
                $attributes['avatar_url'] = $ready->url;
            }
            if ($input['removeProfilePicture'] ?? false) {
                $attributes['avatar_url'] = null;
            }
            if ($locked->isDirty('email')) {
                $locked->email_verified_at = null;
            }
            if (array_key_exists('first_name', $attributes) || array_key_exists('last_name', $attributes)) {
                $locked->name = trim(($attributes['first_name'] ?? $profile->first_name ?? '').' '.($attributes['last_name'] ?? $profile->last_name ?? ''));
            }
            $locked->save();
            $profile->update($attributes);
            if ($sensitive) {
                $locked->tokens()->delete();
            }

            return $locked;
        });
    }
}
