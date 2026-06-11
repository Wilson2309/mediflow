<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['administrador', 'medico', 'recepcionista', 'caja_finanzas'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_guest_cannot_access_users_module(): void
    {
        $this->get(route('users.index'))->assertRedirect(route('login', absolute: false));
    }

    public function test_authenticated_user_can_view_users_index(): void
    {
        $user = $this->userForClinic(Clinic::factory()->create());
        $this->actingAs($user)->get(route('users.index'))->assertOk()->assertSee('Usuarios y Roles');
    }

    public function test_only_users_from_authenticated_users_clinic_are_shown(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $own = $this->userForClinic($clinic, overrides: ['name' => 'Usuario Visible']);
        $other = $this->userForClinic($otherClinic, overrides: ['name' => 'Usuario Oculto']);

        $this->actingAs($this->userForClinic($clinic))
            ->get(route('users.index'))->assertOk()->assertSee($own->name)->assertDontSee($other->name);
    }

    public function test_authenticated_user_can_create_user(): void
    {
        $clinic = Clinic::factory()->create();
        $this->actingAs($this->userForClinic($clinic))
            ->post(route('users.store'), $this->validPayload(['email' => 'nuevo@mediflow.test']))
            ->assertRedirect(route('users.index'))->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['clinic_id' => $clinic->id, 'email' => 'nuevo@mediflow.test', 'status' => 'active']);
    }

    public function test_clinic_id_is_assigned_from_authenticated_user(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $this->actingAs($this->userForClinic($clinic))->post(route('users.store'), [
            ...$this->validPayload(['email' => 'clinica@mediflow.test']),
            'clinic_id' => $otherClinic->id,
        ]);

        $this->assertDatabaseHas('users', ['clinic_id' => $clinic->id, 'email' => 'clinica@mediflow.test']);
        $this->assertDatabaseMissing('users', ['clinic_id' => $otherClinic->id, 'email' => 'clinica@mediflow.test']);
    }

    public function test_selected_role_is_assigned_when_creating_user(): void
    {
        $clinic = Clinic::factory()->create();
        $this->actingAs($this->userForClinic($clinic))->post(route('users.store'), $this->validPayload(['email' => 'medico@mediflow.test', 'role' => 'medico']));

        $this->assertTrue(User::where('email', 'medico@mediflow.test')->firstOrFail()->hasRole('medico'));
    }

    public function test_authenticated_user_can_view_user_detail(): void
    {
        $clinic = Clinic::factory()->create();
        $target = $this->userForClinic($clinic, overrides: ['name' => 'Detalle Usuario']);
        $this->actingAs($this->userForClinic($clinic))->get(route('users.show', $target))->assertOk()->assertSee($target->name);
    }

    public function test_user_cannot_view_user_from_another_clinic(): void
    {
        [$actor, $target] = $this->userAndOtherClinicUser();
        $this->actingAs($actor)->get(route('users.show', $target))->assertForbidden();
    }

    public function test_authenticated_user_can_open_edit_form(): void
    {
        $clinic = Clinic::factory()->create();
        $target = $this->userForClinic($clinic, overrides: ['name' => 'Usuario Editable']);
        $this->actingAs($this->userForClinic($clinic))->get(route('users.edit', $target))->assertOk()->assertSee('Editar usuario');
    }

    public function test_user_cannot_edit_user_from_another_clinic(): void
    {
        [$actor, $target] = $this->userAndOtherClinicUser();
        $this->actingAs($actor)->get(route('users.edit', $target))->assertForbidden();
    }

    public function test_authenticated_user_can_update_user(): void
    {
        $clinic = Clinic::factory()->create();
        $target = $this->userForClinic($clinic, 'recepcionista');
        $this->actingAs($this->userForClinic($clinic))->put(route('users.update', $target), $this->validUpdatePayload($target, [
            'name' => 'Nombre Actualizado', 'phone' => '0999999999', 'status' => 'inactive',
        ]))->assertRedirect(route('users.show', $target))->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['id' => $target->id, 'name' => 'Nombre Actualizado', 'phone' => '0999999999', 'status' => 'inactive']);
    }

    public function test_empty_password_does_not_change_existing_password(): void
    {
        $clinic = Clinic::factory()->create();
        $target = $this->userForClinic($clinic, overrides: ['password' => Hash::make('Original123')]);
        $originalHash = $target->password;

        $this->actingAs($this->userForClinic($clinic))->put(route('users.update', $target), $this->validUpdatePayload($target, ['password' => '', 'password_confirmation' => '']));
        $this->assertSame($originalHash, $target->refresh()->password);
    }

    public function test_provided_password_changes_existing_password(): void
    {
        $clinic = Clinic::factory()->create();
        $target = $this->userForClinic($clinic, overrides: ['password' => Hash::make('Original123')]);

        $this->actingAs($this->userForClinic($clinic))->put(route('users.update', $target), $this->validUpdatePayload($target, ['password' => 'NuevaClave123', 'password_confirmation' => 'NuevaClave123']));
        $this->assertTrue(Hash::check('NuevaClave123', $target->refresh()->password));
    }

    public function test_role_can_be_changed_with_sync_roles(): void
    {
        $clinic = Clinic::factory()->create();
        $target = $this->userForClinic($clinic, 'recepcionista');
        $this->actingAs($this->userForClinic($clinic))->put(route('users.update', $target), $this->validUpdatePayload($target, ['role' => 'caja_finanzas']));

        $target->refresh();
        $this->assertTrue($target->hasRole('caja_finanzas'));
        $this->assertFalse($target->hasRole('recepcionista'));
        $this->assertCount(1, $target->roles);
    }

    public function test_user_cannot_update_user_from_another_clinic(): void
    {
        [$actor, $target] = $this->userAndOtherClinicUser();
        $this->actingAs($actor)->put(route('users.update', $target), $this->validUpdatePayload($target))->assertForbidden();
    }

    public function test_user_cannot_delete_user_from_another_clinic(): void
    {
        [$actor, $target] = $this->userAndOtherClinicUser();
        $this->actingAs($actor)->delete(route('users.destroy', $target))->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_user_cannot_delete_self(): void
    {
        $actor = $this->userForClinic(Clinic::factory()->create());
        $this->actingAs($actor)->delete(route('users.destroy', $actor))->assertSessionHasErrors('user');
        $this->assertDatabaseHas('users', ['id' => $actor->id]);
    }

    public function test_last_administrator_of_clinic_cannot_be_deleted(): void
    {
        $clinic = Clinic::factory()->create();
        $administrator = $this->userForClinic($clinic, 'administrador');
        $actor = $this->userForClinic($clinic, 'recepcionista');

        $this->actingAs($actor)->delete(route('users.destroy', $administrator))->assertSessionHasErrors('user');
        $this->assertDatabaseHas('users', ['id' => $administrator->id]);
    }

    public function test_last_administrator_cannot_change_to_another_role(): void
    {
        $clinic = Clinic::factory()->create();
        $administrator = $this->userForClinic($clinic, 'administrador');
        $actor = $this->userForClinic($clinic, 'recepcionista');

        $this->actingAs($actor)->put(route('users.update', $administrator), $this->validUpdatePayload($administrator, ['role' => 'medico']))->assertSessionHasErrors('role');
        $this->assertTrue($administrator->refresh()->hasRole('administrador'));
    }

    public function test_last_active_administrator_cannot_be_inactivated(): void
    {
        $clinic = Clinic::factory()->create();
        $administrator = $this->userForClinic($clinic, 'administrador');
        $actor = $this->userForClinic($clinic, 'recepcionista');

        $this->actingAs($actor)->put(route('users.update', $administrator), $this->validUpdatePayload($administrator, ['status' => 'inactive']))->assertSessionHasErrors('status');
        $this->assertSame('active', $administrator->refresh()->status);
    }

    public function test_primary_administrator_cannot_be_deleted(): void
    {
        $clinic = Clinic::factory()->create();
        $primary = $this->userForClinic($clinic, 'administrador', ['email' => 'admin@mediflow.com']);
        $this->userForClinic($clinic, 'administrador');
        $actor = $this->userForClinic($clinic, 'recepcionista');

        $this->actingAs($actor)->delete(route('users.destroy', $primary))->assertSessionHasErrors('user');
        $this->assertDatabaseHas('users', ['id' => $primary->id]);
    }

    public function test_required_name_is_validated(): void
    {
        $this->actingAs($this->userForClinic(Clinic::factory()->create()))->post(route('users.store'), $this->validPayload(['name' => '']))->assertSessionHasErrors('name');
    }

    public function test_email_must_be_unique(): void
    {
        $clinic = Clinic::factory()->create();
        $existing = $this->userForClinic($clinic);
        $this->actingAs($this->userForClinic($clinic))->post(route('users.store'), $this->validPayload(['email' => $existing->email]))->assertSessionHasErrors('email');
    }

    public function test_password_must_be_confirmed(): void
    {
        $this->actingAs($this->userForClinic(Clinic::factory()->create()))->post(route('users.store'), $this->validPayload(['password_confirmation' => 'Diferente123']))->assertSessionHasErrors('password');
    }

    public function test_role_must_be_valid(): void
    {
        $this->actingAs($this->userForClinic(Clinic::factory()->create()))->post(route('users.store'), $this->validPayload(['role' => 'superadmin']))->assertSessionHasErrors('role');
    }

    public function test_search_by_name_works(): void
    {
        $clinic = Clinic::factory()->create();
        $matching = $this->userForClinic($clinic, overrides: ['name' => 'Nombre Encontrable']);
        $other = $this->userForClinic($clinic, overrides: ['name' => 'Nombre Oculto']);
        $this->actingAs($this->userForClinic($clinic))->get(route('users.index', ['search' => 'Encontrable']))->assertSee($matching->name)->assertDontSee($other->name);
    }

    public function test_search_by_email_works(): void
    {
        $clinic = Clinic::factory()->create();
        $matching = $this->userForClinic($clinic, overrides: ['email' => 'buscar.usuario@mediflow.test']);
        $other = $this->userForClinic($clinic, overrides: ['email' => 'oculto.usuario@mediflow.test']);
        $this->actingAs($this->userForClinic($clinic))->get(route('users.index', ['search' => 'buscar.usuario']))->assertSee($matching->email)->assertDontSee($other->email);
    }

    public function test_filter_by_role_works(): void
    {
        $clinic = Clinic::factory()->create();
        $doctor = $this->userForClinic($clinic, 'medico');
        $receptionist = $this->userForClinic($clinic, 'recepcionista');
        $this->actingAs($this->userForClinic($clinic))->get(route('users.index', ['role' => 'medico']))
            ->assertViewHas('users', fn ($users) => $users->contains('id', $doctor->id) && ! $users->contains('id', $receptionist->id));
    }

    public function test_filter_by_status_works(): void
    {
        $clinic = Clinic::factory()->create();
        $active = $this->userForClinic($clinic, overrides: ['status' => 'active']);
        $inactive = $this->userForClinic($clinic, overrides: ['status' => 'inactive']);
        $this->actingAs($this->userForClinic($clinic))->get(route('users.index', ['status' => 'inactive']))
            ->assertViewHas('users', fn ($users) => $users->contains('id', $inactive->id) && ! $users->contains('id', $active->id));
    }

    public function test_inactive_user_is_rendered_with_status_badge(): void
    {
        $clinic = Clinic::factory()->create();
        $inactive = $this->userForClinic($clinic, overrides: ['name' => 'Usuario Inactivo Badge', 'status' => 'inactive']);
        $this->actingAs($this->userForClinic($clinic))->get(route('users.index'))->assertOk()->assertSee($inactive->name)->assertSee('Inactivo');
    }

    public function test_sidebar_links_to_users_module(): void
    {
        $user = $this->userForClinic(Clinic::factory()->create());
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Usuarios y Roles')->assertSee(route('users.index'), escape: false);
    }

    public function test_non_protected_user_can_be_deleted(): void
    {
        $clinic = Clinic::factory()->create();
        $actor = $this->userForClinic($clinic, 'administrador');
        $target = $this->userForClinic($clinic, 'recepcionista');
        $this->actingAs($actor)->delete(route('users.destroy', $target))->assertRedirect(route('users.index'))->assertSessionHas('success');
        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_last_administrator_cannot_delete_account_from_profile(): void
    {
        $administrator = $this->userForClinic(Clinic::factory()->create(), 'administrador');

        $this->actingAs($administrator)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrorsIn('userDeletion', 'password');

        $this->assertDatabaseHas('users', ['id' => $administrator->id]);
    }

    private function userForClinic(Clinic $clinic, string $role = 'recepcionista', array $overrides = []): User
    {
        $user = User::factory()->create(['clinic_id' => $clinic->id, ...$overrides]);
        $user->syncRoles([$role]);

        return $user;
    }

    /** @return array{0: User, 1: User} */
    private function userAndOtherClinicUser(): array
    {
        return [
            $this->userForClinic(Clinic::factory()->create()),
            $this->userForClinic(Clinic::factory()->create()),
        ];
    }

    /** @param array<string, mixed> $overrides */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Usuario Nuevo',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '0991234567',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'role' => 'recepcionista',
            'status' => 'active',
        ], $overrides);
    }

    /** @param array<string, mixed> $overrides */
    private function validUpdatePayload(User $user, array $overrides = []): array
    {
        return array_merge([
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'password' => '',
            'password_confirmation' => '',
            'role' => $user->getRoleNames()->first() ?? 'recepcionista',
            'status' => $user->status,
        ], $overrides);
    }
}
