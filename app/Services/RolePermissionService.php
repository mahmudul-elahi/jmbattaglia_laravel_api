<?php

namespace App\Services;

use App\Repositories\Eloquent\RolePermissionRepository;
use Illuminate\Database\Eloquent\Collection;

final class RolePermissionService
{
    public function __construct(private RolePermissionRepository $repository) {}

    public function roles(): Collection
    {
        return $this->repository->roles();
    }

    public function permissions(): Collection
    {
        return $this->repository->permissions();
    }

    public function assign(int $userId, string $role, int $actor): void
    {
        $this->repository->assign($userId, $role, $actor);
    }

    public function grants(int $roleId, array $permissions, int $actor): void
    {
        $this->repository->grants($roleId, $permissions, $actor);
    }
}
