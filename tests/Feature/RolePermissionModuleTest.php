<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Patient;
use App\Models\User;
use App\Support\RolePermissions;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_permission_seeder_is_idempotent_and_assigns_all_permissions_to_administrator(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->assertSame(count(RolePermissions::all()), Permission::count());
        $this->assertCount(count(RolePermissions::all()), Role::findByName('administrador')->permissions);
    }

    public function test_permission_seeder_assigns_the_exact_permissions_for_each_role(): void
    {
        foreach (RolePermissions::byRole() as $roleName => $expectedPermissions) {
            $actualPermissions = Role::findByName($roleName)->permissions->pluck('name')->all();

            $this->assertEqualsCanonicalizing($expectedPermissions, $actualPermissions, "Permisos incorrectos para {$roleName}.");
        }
    }

    public function test_permission_seeder_repairs_the_primary_administrator_role(): void
    {
        $this->seed(AdminUserSeeder::class);
        $admin = User::where('email', 'admin@mediflow.com')->firstOrFail();
        $admin->syncRoles(['recepcionista']);

        $this->seed(PermissionSeeder::class);

        $this->assertTrue($admin->fresh()->hasRole('administrador'));
        $this->assertFalse($admin->fresh()->hasRole('recepcionista'));
        $this->assertCount(count(RolePermissions::all()), $admin->fresh()->getAllPermissions());
    }

    public function test_administrator_can_access_dashboard_and_all_main_modules(): void
    {
        $user = $this->userWithRole('administrador');

        foreach ([
            'dashboard', 'patients.index', 'doctors.index', 'services.index',
            'appointments.index', 'consultations.index', 'medical-records.index',
            'prescriptions.index', 'payments.index', 'reports.index', 'users.index',
            'settings.clinic.edit',
        ] as $routeName) {
            $this->actingAs($user)->get(route($routeName))->assertOk();
        }
    }

    public function test_medico_permissions_are_enforced(): void
    {
        $user = $this->userWithRole('medico');

        $this->actingAs($user)->get(route('consultations.index'))->assertOk();
        $this->actingAs($user)->get(route('consultations.create'))->assertOk();
        $this->actingAs($user)->get(route('prescriptions.index'))->assertOk();
        $this->actingAs($user)->get(route('reports.clinical'))->assertOk();
        $this->actingAs($user)->get(route('users.index'))->assertForbidden();
        $this->actingAs($user)->get(route('payments.index'))->assertForbidden();
        $this->actingAs($user)->get(route('reports.financial'))->assertForbidden();
    }

    public function test_receptionist_permissions_are_enforced(): void
    {
        $user = $this->userWithRole('recepcionista');

        $this->actingAs($user)->get(route('patients.index'))->assertOk();
        $this->actingAs($user)->get(route('patients.create'))->assertOk();
        $this->actingAs($user)->get(route('appointments.index'))->assertOk();
        $this->actingAs($user)->get(route('appointments.create'))->assertOk();
        $this->actingAs($user)->get(route('payments.index'))->assertForbidden();
        $this->actingAs($user)->get(route('consultations.index'))->assertForbidden();
    }

    public function test_cashier_permissions_are_enforced(): void
    {
        $user = $this->userWithRole('caja_finanzas');

        $this->actingAs($user)->get(route('payments.index'))->assertOk();
        $this->actingAs($user)->get(route('payments.create'))->assertOk();
        $this->actingAs($user)->get(route('reports.financial'))->assertOk();
        $this->actingAs($user)->get(route('reports.services'))->assertForbidden();
        $this->actingAs($user)->get(route('medical-records.index'))->assertForbidden();
        $this->actingAs($user)->get(route('prescriptions.create'))->assertForbidden();
    }

    public function test_user_without_permission_receives_403_from_direct_url(): void
    {
        $role = Role::firstOrCreate(['name' => 'sin_permisos', 'guard_name' => 'web']);
        $user = User::factory()->for(Clinic::factory())->create();
        $user->assignRole($role);

        $this->actingAs($user)->get(route('patients.index'))->assertForbidden();
    }

    public function test_sidebar_hides_links_without_permission(): void
    {
        $user = $this->userWithRole('medico');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('consultations.index'), escape: false)
            ->assertDontSee(route('users.index'), escape: false)
            ->assertDontSee(route('payments.index'), escape: false)
            ->assertDontSee(route('settings.clinic.edit'), escape: false);
    }

    public function test_create_update_and_delete_buttons_are_hidden_without_permissions(): void
    {
        $user = $this->userWithRole('caja_finanzas');
        $patient = Patient::factory()->create(['clinic_id' => $user->clinic_id]);

        $this->actingAs($user)
            ->get(route('patients.index'))
            ->assertOk()
            ->assertDontSee('Nuevo paciente')
            ->assertDontSee('Editar')
            ->assertDontSee('Eliminar');
    }

    public function test_permissions_do_not_replace_clinic_isolation(): void
    {
        $user = $this->userWithRole('administrador');
        $otherPatient = Patient::factory()->for(Clinic::factory())->create();

        $this->actingAs($user)->get(route('patients.show', $otherPatient))->assertForbidden();
    }

    public function test_last_administrator_remains_protected(): void
    {
        $clinic = Clinic::factory()->create();
        $administrator = $this->userWithRole('administrador', $clinic);
        $operator = User::factory()->create(['clinic_id' => $clinic->id]);
        $operator->assignRole('recepcionista');
        $operator->givePermissionTo(['users.view', 'users.delete']);

        $this->actingAs($operator)
            ->delete(route('users.destroy', $administrator))
            ->assertRedirect()
            ->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', ['id' => $administrator->id]);
    }

    private function userWithRole(string $role, ?Clinic $clinic = null): User
    {
        $user = User::factory()->create(['clinic_id' => ($clinic ?? Clinic::factory()->create())->id]);
        $user->syncRoles([$role]);

        return $user;
    }
}
