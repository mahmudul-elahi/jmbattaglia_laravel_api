<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class UserRepository implements UserRepositoryInterface
{
    public function findForLogin(string $email): ?User
    {
        return User::query()->with(['roles.permissions', 'permissions', 'profile'])->where('email', $email)->first();
    }

    public function register(array $input): User
    {
        return DB::transaction(function () use ($input): User {
            abort_if(User::query()->where('email', $input['email'])->exists(), 409, 'Email already registered.');
            $user = User::query()->create(['name' => $input['fullName'], 'email' => $input['email'], 'password' => $input['password']]);
            $user->profile()->create(['first_name' => $input['fullName']]);
            $user->syncRoles(['USER']);

            return $user->load(['roles.permissions', 'permissions', 'profile']);
        });
    }
}
