<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Eloquent\ProfileRepository;
use App\Support\PasswordVerifier;

final class ProfileService
{
    public function __construct(private MediaService $media, private ProfileRepository $repository) {}

    public function read(User $user): array
    {
        $user = $this->repository->load($user);

        return ['id' => (int) $user->id, 'name' => $user->name, 'email' => $user->email, 'avatarUrl' => $user->profile?->avatar_url, 'firstName' => $user->profile?->first_name, 'lastName' => $user->profile?->last_name];
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
            $updated = $this->repository->update($user, $input, $sensitive, $uploaded);
        } catch (\Throwable $error) {
            if ($uploaded) {
                $uploaded->update(['status' => 'DELETED']);
            } throw $error;
        }

        return $this->read($updated) + ['requiresReauthentication' => $sensitive];
    }
}
