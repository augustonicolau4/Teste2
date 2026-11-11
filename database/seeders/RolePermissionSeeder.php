<?php

namespace Database\Seeders;

use App\Domain\Core\Enums\Permission;
use App\Domain\Core\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Sincroniza as permissões granulares e os 6 perfis (RBAC) do autogest_nicola.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 1. Permissões
        foreach (Permission::cases() as $permission) {
            PermissionModel::findOrCreate($permission->value, 'web');
        }

        // 2. Perfis + atribuição das permissões
        foreach (UserRole::cases() as $role) {
            $model = Role::findOrCreate($role->value, 'web');

            $model->syncPermissions($role->permissionValues());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
