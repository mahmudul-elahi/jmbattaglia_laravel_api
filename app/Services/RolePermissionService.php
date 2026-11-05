<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class RolePermissionService
{
    public function roles(): Collection
    {
        return Role::query()->with('permissions')->orderBy('name')->get();
    }

    public function permissions(): Collection
    {
        return Permission::query()->where('guard_name', 'web')->orderBy('name')->get();
    }

    public function assign(int $userId, string $role, int $actor): void
    {
        try {
            DB::transaction(function () use ($userId, $role): void {
                $this->lockAdministratorRole();
                $user = User::query()->lockForUpdate()->findOrFail($userId);
                $user->syncRoles([$role]);
                $this->assertUsableAdministrator();
            });
        } finally {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function grants(int $roleId, array $permissions, int $actor): void
    {
        try {
            DB::transaction(function () use ($roleId, $permissions): void {
                $this->lockAdministratorRole();
                $role = Role::query()->where('guard_name', 'web')->lockForUpdate()->findOrFail($roleId);
                $role->syncPermissions($permissions);
                $this->assertUsableAdministrator();
            });
        } finally {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    private function lockAdministratorRole(): void
    {
        Role::query()->where('name', 'ADMIN')->where('guard_name', 'web')->lockForUpdate()->firstOrFail();
    }

    private function assertUsableAdministrator(): void
    {
        $admins = User::role('ADMIN', 'web')->whereNotNull('password')->with(['roles.permissions', 'permissions'])->get();
        abort_unless($admins->contains(fn (User $user): bool => $user->can('roles.manage')), 409, 'At least one usable administrator must retain role management access.');
    }
}
