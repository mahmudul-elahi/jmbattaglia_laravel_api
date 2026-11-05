<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $names = ['dashboard.view', 'catalog.manage', 'content.manage', 'media.manage', 'settings.manage', 'profile.manage', 'submissions.view', 'submissions.reconcile', 'integrations.manage', 'clients.manage', 'roles.manage'];
        foreach ($names as $name) {
            Permission::findOrCreate($name, 'web');
        }
        $admin = Role::findOrCreate('ADMIN', 'web');
        $admin->syncPermissions($names);
        Role::findOrCreate('USER', 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
