<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DoctorModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::firstOrCreate([
            'name' => 'medico',
            'guard_name' => 'web',
        ]);
    }

    public function test_guest_cannot_access_doctors_index(): void
    {
        $this->get(route('doctors.index'))
            ->assertRedirect(route('login', absolute: false));
    }

    public function test_authenticated_user_can_see_doctors_from_own_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $ownDoctor = $this->doctorForClinic($clinic, ['name' => 'Dra. Propia']);
        $otherDoctor = $this->doctorForClinic($otherClinic, ['name' => 'Dr. Externo']);

        $this->actingAs($user)
            ->get(route('doctors.index'))
            ->assertOk()
            ->assertSee($ownDoctor->user->name)
            ->assertDontSee($otherDoctor->user->name);
    }

    public function test_authenticated_user_can_open_create_doctor_form(): void
    {
        $user = $this->userForClinic(Clinic::factory()->create());

        $this->actingAs($user)
            ->get(route('doctors.create'))
            ->assertOk()
            ->assertSee('Nuevo medico');
    }

    public function test_authenticated_user_can_create_valid_doctor_and_user_with_medico_role(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $specialty = Specialty::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('doctors.store'), $this->validDoctorPayload([
                'name' => 'Dr. Nuevo',
                'email' => 'doctor.nuevo@mediflow.test',
                'specialty_id' => $specialty->id,
                'license_number' => 'LIC-1001',
            ]));

        $response
            ->assertRedirect(route('doctors.index'))
            ->assertSessionHas('success', 'Medico creado correctamente.');

        $doctorUser = User::where('email', 'doctor.nuevo@mediflow.test')->first();

        $this->assertNotNull($doctorUser);
        $this->assertTrue($doctorUser->hasRole('medico'));
        $this->assertDatabaseHas('doctors', [
            'clinic_id' => $clinic->id,
            'user_id' => $doctorUser->id,
            'specialty_id' => $specialty->id,
            'license_number' => 'LIC-1001',
            'status' => 'active',
        ]);
    }

    public function test_doctor_cannot_be_created_without_name(): void
    {
        $user = $this->userForClinic(Clinic::factory()->create());

        $this->actingAs($user)
            ->from(route('doctors.create'))
            ->post(route('doctors.store'), $this->validDoctorPayload(['name' => '']))
            ->assertRedirect(route('doctors.create'))
            ->assertSessionHasErrors('name');
    }

    public function test_doctor_cannot_be_created_without_email(): void
    {
        $user = $this->userForClinic(Clinic::factory()->create());

        $this->actingAs($user)
            ->from(route('doctors.create'))
            ->post(route('doctors.store'), $this->validDoctorPayload(['email' => '']))
            ->assertRedirect(route('doctors.create'))
            ->assertSessionHasErrors('email');
    }

    public function test_doctor_cannot_be_created_without_password(): void
    {
        $user = $this->userForClinic(Clinic::factory()->create());

        $payload = $this->validDoctorPayload([
            'password' => '',
            'password_confirmation' => '',
        ]);

        $this->actingAs($user)
            ->from(route('doctors.create'))
            ->post(route('doctors.store'), $payload)
            ->assertRedirect(route('doctors.create'))
            ->assertSessionHasErrors('password');
    }

    public function test_doctor_email_must_be_unique(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        User::factory()->create(['email' => 'duplicado@mediflow.test']);

        $this->actingAs($user)
            ->from(route('doctors.create'))
            ->post(route('doctors.store'), $this->validDoctorPayload(['email' => 'duplicado@mediflow.test']))
            ->assertRedirect(route('doctors.create'))
            ->assertSessionHasErrors('email');
    }

    public function test_doctor_license_number_must_be_unique(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $this->doctorForClinic($clinic, ['license_number' => 'LIC-DUP']);

        $this->actingAs($user)
            ->from(route('doctors.create'))
            ->post(route('doctors.store'), $this->validDoctorPayload(['license_number' => 'LIC-DUP']))
            ->assertRedirect(route('doctors.create'))
            ->assertSessionHasErrors('license_number');
    }

    public function test_authenticated_user_can_view_doctor_from_own_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $doctor = $this->doctorForClinic($clinic);

        $this->actingAs($user)
            ->get(route('doctors.show', $doctor))
            ->assertOk()
            ->assertSee($doctor->user->name);
    }

    public function test_authenticated_user_can_open_edit_form_for_own_doctor(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $doctor = $this->doctorForClinic($clinic);

        $this->actingAs($user)
            ->get(route('doctors.edit', $doctor))
            ->assertOk()
            ->assertSee('Editar medico')
            ->assertSee($doctor->user->name);
    }

    public function test_authenticated_user_can_update_doctor_from_own_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $doctor = $this->doctorForClinic($clinic, ['license_number' => 'LIC-UPD']);
        $specialty = Specialty::factory()->create(['name' => 'Cardiologia']);

        $response = $this->actingAs($user)
            ->put(route('doctors.update', $doctor), $this->validDoctorPayload([
                'name' => 'Dr. Actualizado',
                'email' => $doctor->user->email,
                'password' => null,
                'password_confirmation' => null,
                'specialty_id' => $specialty->id,
                'license_number' => 'LIC-UPD',
                'phone' => '0988887777',
                'consultation_fee' => '75.50',
                'status' => 'inactive',
            ]));

        $response
            ->assertRedirect(route('doctors.show', $doctor))
            ->assertSessionHas('success', 'Medico actualizado correctamente.');

        $this->assertDatabaseHas('users', [
            'id' => $doctor->user_id,
            'name' => 'Dr. Actualizado',
        ]);

        $this->assertDatabaseHas('doctors', [
            'id' => $doctor->id,
            'clinic_id' => $clinic->id,
            'specialty_id' => $specialty->id,
            'phone' => '0988887777',
            'status' => 'inactive',
        ]);
    }

    public function test_password_is_not_changed_when_update_password_is_empty(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $doctor = $this->doctorForClinic($clinic);
        $originalPassword = $doctor->user->password;

        $this->actingAs($user)
            ->put(route('doctors.update', $doctor), $this->validDoctorPayload([
                'name' => $doctor->user->name,
                'email' => $doctor->user->email,
                'password' => null,
                'password_confirmation' => null,
                'license_number' => $doctor->license_number,
            ]))
            ->assertRedirect(route('doctors.show', $doctor));

        $this->assertSame($originalPassword, $doctor->user->fresh()->password);
    }

    public function test_authenticated_user_can_delete_doctor_from_own_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $doctor = $this->doctorForClinic($clinic);
        $doctorUserId = $doctor->user_id;

        $this->actingAs($user)
            ->delete(route('doctors.destroy', $doctor))
            ->assertRedirect(route('doctors.index'))
            ->assertSessionHas('success', 'Medico eliminado correctamente.');

        $this->assertDatabaseMissing('doctors', [
            'id' => $doctor->id,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $doctorUserId,
        ]);
    }

    public function test_search_by_doctor_name_works(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $matchingDoctor = $this->doctorForClinic($clinic, ['name' => 'Doctor Buscable']);
        $otherDoctor = $this->doctorForClinic($clinic, ['name' => 'Doctor Oculto']);

        $this->actingAs($user)
            ->get(route('doctors.index', ['search' => 'Buscable']))
            ->assertOk()
            ->assertSee($matchingDoctor->user->name)
            ->assertDontSee($otherDoctor->user->name);
    }

    public function test_search_by_license_number_works(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $matchingDoctor = $this->doctorForClinic($clinic, ['license_number' => 'LIC-SEARCH-1']);
        $otherDoctor = $this->doctorForClinic($clinic, ['license_number' => 'LIC-SEARCH-2']);

        $this->actingAs($user)
            ->get(route('doctors.index', ['search' => 'LIC-SEARCH-1']))
            ->assertOk()
            ->assertSee($matchingDoctor->license_number)
            ->assertDontSee($otherDoctor->license_number);
    }

    public function test_filter_by_active_status_works(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $activeDoctor = $this->doctorForClinic($clinic, ['name' => 'Doctor Activo Visible', 'status' => 'active']);
        $inactiveDoctor = $this->doctorForClinic($clinic, ['name' => 'Doctor Inactivo Oculto', 'status' => 'inactive']);

        $this->actingAs($user)
            ->get(route('doctors.index', ['status' => 'active']))
            ->assertOk()
            ->assertSee($activeDoctor->user->name)
            ->assertDontSee($inactiveDoctor->user->name);
    }

    public function test_filter_by_inactive_status_works(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $activeDoctor = $this->doctorForClinic($clinic, ['name' => 'Doctor Activo Oculto', 'status' => 'active']);
        $inactiveDoctor = $this->doctorForClinic($clinic, ['name' => 'Doctor Inactivo Visible', 'status' => 'inactive']);

        $this->actingAs($user)
            ->get(route('doctors.index', ['status' => 'inactive']))
            ->assertOk()
            ->assertSee($inactiveDoctor->user->name)
            ->assertDontSee($activeDoctor->user->name);
    }

    public function test_filter_by_specialty_id_works(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $specialty = Specialty::factory()->create(['name' => 'Pediatria']);
        $otherSpecialty = Specialty::factory()->create(['name' => 'Dermatologia']);
        $matchingDoctor = $this->doctorForClinic($clinic, ['name' => 'Doctor Pediatra', 'specialty_id' => $specialty->id]);
        $otherDoctor = $this->doctorForClinic($clinic, ['name' => 'Doctor Dermatologo', 'specialty_id' => $otherSpecialty->id]);

        $this->actingAs($user)
            ->get(route('doctors.index', ['specialty_id' => $specialty->id]))
            ->assertOk()
            ->assertSee($matchingDoctor->user->name)
            ->assertDontSee($otherDoctor->user->name);
    }

    public function test_user_cannot_view_doctor_from_another_clinic(): void
    {
        [$user, $otherDoctor] = $this->userAndOtherClinicDoctor();

        $this->actingAs($user)
            ->get(route('doctors.show', $otherDoctor))
            ->assertForbidden();
    }

    public function test_user_cannot_edit_doctor_from_another_clinic(): void
    {
        [$user, $otherDoctor] = $this->userAndOtherClinicDoctor();

        $this->actingAs($user)
            ->get(route('doctors.edit', $otherDoctor))
            ->assertForbidden();
    }

    public function test_user_cannot_update_doctor_from_another_clinic(): void
    {
        [$user, $otherDoctor] = $this->userAndOtherClinicDoctor();

        $this->actingAs($user)
            ->put(route('doctors.update', $otherDoctor), $this->validDoctorPayload([
                'email' => $otherDoctor->user->email,
                'password' => null,
                'password_confirmation' => null,
                'license_number' => $otherDoctor->license_number,
            ]))
            ->assertForbidden();
    }

    public function test_user_cannot_delete_doctor_from_another_clinic(): void
    {
        [$user, $otherDoctor] = $this->userAndOtherClinicDoctor();

        $this->actingAs($user)
            ->delete(route('doctors.destroy', $otherDoctor))
            ->assertForbidden();

        $this->assertDatabaseHas('doctors', [
            'id' => $otherDoctor->id,
        ]);
    }

    private function userForClinic(Clinic $clinic): User
    {
        return User::factory()->create([
            'clinic_id' => $clinic->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function doctorForClinic(Clinic $clinic, array $overrides = []): Doctor
    {
        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
            'name' => $overrides['name'] ?? fake()->name(),
            'email' => $overrides['email'] ?? fake()->unique()->safeEmail(),
            'password' => Hash::make('OriginalPass123'),
        ]);

        $specialtyId = array_key_exists('specialty_id', $overrides)
            ? $overrides['specialty_id']
            : Specialty::factory()->create()->id;

        return Doctor::factory()
            ->for($clinic)
            ->for($user)
            ->create([
                'specialty_id' => $specialtyId,
                'license_number' => $overrides['license_number'] ?? fake()->unique()->bothify('LIC-####??'),
                'phone' => $overrides['phone'] ?? '0991112222',
                'consultation_fee' => $overrides['consultation_fee'] ?? 40.00,
                'status' => $overrides['status'] ?? 'active',
            ]);
    }

    /**
     * @return array{0: User, 1: Doctor}
     */
    private function userAndOtherClinicDoctor(): array
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();

        return [
            $this->userForClinic($clinic),
            $this->doctorForClinic($otherClinic),
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validDoctorPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Dr. Test',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'specialty_id' => Specialty::factory()->create()->id,
            'license_number' => 'LIC-'.fake()->unique()->numerify('######'),
            'phone' => '0991234567',
            'consultation_fee' => '40.00',
            'status' => 'active',
        ], $overrides);
    }
}
