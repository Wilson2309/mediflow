<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $tables = config('permission.table_names');
        $requiredTableKeys = [
            'permissions',
            'roles',
            'role_has_permissions',
        ];

        foreach ($requiredTableKeys as $key) {
            $table = $tables[$key] ?? null;

            if (! is_string($table) || $table === '' || ! Schema::hasTable($table)) {
                throw new RuntimeException('Essential permission tables are unavailable.');
            }
        }

        $permissionsTable = $tables['permissions'];
        $rolesTable = $tables['roles'];
        $rolePermissionsTable = $tables['role_has_permissions'];

        DB::transaction(function () use ($permissionsTable, $rolesTable, $rolePermissionsTable): void {
            $roleCount = DB::table($rolesTable)->count();
            $medicoRole = DB::table($rolesTable)
                ->where('name', 'medico')
                ->where('guard_name', 'web')
                ->lockForUpdate()
                ->first(['id']);

            if (! $medicoRole) {
                if ($roleCount === 0) {
                    Log::warning('Prescription sign permission deferred until roles are seeded.');

                    return;
                }

                throw new RuntimeException('The medico role is required before assigning prescriptions.sign.');
            }

            $permission = DB::table($permissionsTable)
                ->where('name', 'prescriptions.sign')
                ->where('guard_name', 'web')
                ->lockForUpdate()
                ->first(['id']);

            $permissionId = $permission?->id;

            if (! $permissionId) {
                $permissionId = DB::table($permissionsTable)->insertGetId([
                    'name' => 'prescriptions.sign',
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table($rolePermissionsTable)->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $medicoRole->id,
            ]);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Non-destructive by design: this migration cannot distinguish a shared or pre-existing ACL.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
