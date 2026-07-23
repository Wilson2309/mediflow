<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PrescriptionPermissionMigrationSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_permission_migration_is_idempotent_and_invalidates_spatie_cache(): void
    {
        Permission::findByName('prescriptions.sign')->delete();
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();
        $registrar->getPermissions();
        $migration = $this->migration();

        $migration->up();
        $migration->up();

        $permission = Permission::findByName('prescriptions.sign');
        $medico = Role::findByName('medico');
        $this->assertSame(1, Permission::query()
            ->where('name', 'prescriptions.sign')
            ->where('guard_name', 'web')
            ->count());
        $this->assertSame(1, DB::table(config('permission.table_names.role_has_permissions'))
            ->where('permission_id', $permission->id)
            ->where('role_id', $medico->id)
            ->count());
        $this->assertTrue($medico->hasPermissionTo('prescriptions.sign'));
    }

    public function test_permission_migration_fails_without_medico_and_leaves_no_partial_acl(): void
    {
        Permission::findByName('prescriptions.sign')->delete();
        Role::findByName('medico')->delete();
        $failed = false;

        try {
            $this->migration()->up();
        } catch (\RuntimeException) {
            $failed = true;
        }

        $this->assertTrue($failed);
        $this->assertDatabaseMissing('permissions', [
            'name' => 'prescriptions.sign',
            'guard_name' => 'web',
        ]);
    }

    public function test_permission_migration_defers_verifiably_when_roles_table_is_empty(): void
    {
        $tables = config('permission.table_names');
        DB::table($tables['role_has_permissions'])->delete();
        DB::table($tables['model_has_roles'])->delete();
        DB::table($tables['roles'])->delete();
        Permission::query()->where('name', 'prescriptions.sign')->delete();
        Log::spy();

        $this->migration()->up();

        $this->assertDatabaseMissing('permissions', [
            'name' => 'prescriptions.sign',
            'guard_name' => 'web',
        ]);
        Log::shouldHaveReceived('warning')
            ->with('Prescription sign permission deferred until roles are seeded.')
            ->once();
    }

    public function test_permission_migration_fails_when_an_essential_table_is_unavailable(): void
    {
        $originalTables = config('permission.table_names');
        $changedTables = $originalTables;
        $changedTables['permissions'] = 'missing_permission_table_for_test';
        config(['permission.table_names' => $changedTables]);
        $failed = false;

        try {
            $this->migration()->up();
        } catch (\RuntimeException) {
            $failed = true;
        } finally {
            config(['permission.table_names' => $originalTables]);
        }

        $this->assertTrue($failed);
        $this->assertSame(1, Permission::query()
            ->where('name', 'prescriptions.sign')
            ->where('guard_name', 'web')
            ->count());
    }

    public function test_permission_migration_down_preserves_preexisting_permission_and_acl(): void
    {
        $permission = Permission::findByName('prescriptions.sign');
        $medico = Role::findByName('medico');
        $administrator = Role::findByName('administrador');
        $administrator->givePermissionTo($permission);
        $user = User::factory()->for(Clinic::factory())->create();
        $user->givePermissionTo($permission);
        $migration = $this->migration();

        $migration->up();
        $migration->down();

        $this->assertDatabaseHas('permissions', ['id' => $permission->id]);
        $this->assertDatabaseHas(config('permission.table_names.role_has_permissions'), [
            'permission_id' => $permission->id,
            'role_id' => $medico->id,
        ]);
        $this->assertDatabaseHas(config('permission.table_names.role_has_permissions'), [
            'permission_id' => $permission->id,
            'role_id' => $administrator->id,
        ]);
        $this->assertDatabaseHas(config('permission.table_names.model_has_permissions'), [
            'permission_id' => $permission->id,
            'model_type' => User::class,
            'model_id' => $user->id,
        ]);
        $this->assertTrue($user->fresh()->hasPermissionTo('prescriptions.sign'));
    }

    private function migration(): object
    {
        return require database_path(
            'migrations/2026_07_19_000001_add_prescriptions_sign_permission.php',
        );
    }
}
