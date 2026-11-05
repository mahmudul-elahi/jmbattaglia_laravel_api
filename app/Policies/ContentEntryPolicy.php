<?php

namespace App\Policies;

use App\Models\ContentEntry;
use App\Models\User;

class ContentEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('ADMIN') && $user->can('content.manage');
    }

    public function view(User $user, ContentEntry $content): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, ContentEntry $content): bool
    {
        return $this->viewAny($user);
    }

    public function publish(User $user, ContentEntry $content): bool
    {
        return $this->update($user, $content);
    }
}
