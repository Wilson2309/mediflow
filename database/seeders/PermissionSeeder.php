<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\RolePermissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::transaction(function (): void {
            foreach (RolePermissions::all() as $permission) {
                Permission::firstOrCreate([
                    'name' => $permission,
                    'guard_name' => 'web',
                ]);
            }

            foreach (RolePermissions::byRole() as $roleName => $permissions) {
                $role = Role::firstOrCreate([
                    'name' => $roleName,
                    'guard_name' => 'web',
                ]);

                $role->syncPermissions($permissions);
            }

            User::whereRaw('LOWER(email) = ?', ['admin@mediflow.com'])
                ->first()
                ?->syncRoles(['administrador']);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
