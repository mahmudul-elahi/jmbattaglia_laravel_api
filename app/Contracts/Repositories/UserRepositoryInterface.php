<?php

namespace App\Contracts\Repositories;

use App\Models\User;

interface UserRepositoryInterface
{
    public function findForLogin(string $email): ?User;

    public function register(array $input): User;
}
