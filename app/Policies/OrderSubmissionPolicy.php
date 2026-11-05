<?php

namespace App\Policies;

use App\Models\OrderSubmission;
use App\Models\User;

class OrderSubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('ADMIN') && $user->can('submissions.view');
    }

    public function view(User $user, OrderSubmission $submission): bool
    {
        return $this->viewAny($user);
    }

    public function reconcile(User $user, OrderSubmission $submission): bool
    {
        return $user->hasRole('ADMIN') && $user->can('submissions.reconcile');
    }
}
