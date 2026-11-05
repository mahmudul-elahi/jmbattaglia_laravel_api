<?php

namespace App\Services;

use App\Models\MediaAsset;
use App\Models\User;
use App\Support\PasswordVerifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProfileService
{
    public function __construct(private MediaService $media) {}

    public function read(User $user): array
    {
        $user = $user->load('profile');

        return [
            'id' => (int) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatarUrl' => $user->profile?->avatar_url,
            'firstName' => $user->profile?->first_name,
            'lastName' => $user->profile?->last_name,
        ];
    }

    public function update(User $user, array $input): array
    {
        abort_if(($input['removeProfilePicture'] ?? false) && (isset($input['profilePicture']) || isset($input['avatarMediaId'])), 400, 'Choose a picture or remove it.');
        $sensitive = (isset($input['email']) && $input['email'] !== $user->email) || isset($input['newPassword']);
        if ($sensitive) {
            abort_unless($user->password && PasswordVerifier::check($input['currentPassword'] ?? '', $user->password), 401, 'Current password is incorrect.');
        }
        $uploaded = isset($input['profilePicture']) ? $this->media->upload($input['profilePicture'], $user->id) : null;
        try {
            $updated = $this->performUpdate($user, $input, $sensitive, $uploaded);
        } catch (\Throwable $error) {
            if ($uploaded) {
                $uploaded->update(['status' => 'DELETED']);
            }
            throw $error;
        }

        return $this->read($updated) + ['requiresReauthentication' => $sensitive];
    }

    private function performUpdate(User $user, array $input, bool $sensitive, ?MediaAsset $uploaded): User
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
