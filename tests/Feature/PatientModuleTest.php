<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_patients_index(): void
    {
        $this->get(route('patients.index'))
            ->assertRedirect(route('login', absolute: false));
    }

    public function test_authenticated_user_can_see_patients_from_own_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $ownPatient = Patient::factory()->for($clinic)->create(['first_name' => 'Ana', 'last_name' => 'Lopez']);
        $otherPatient = Patient::factory()->for($otherClinic)->create(['first_name' => 'Carlos', 'last_name' => 'Mendoza']);

        $this->actingAs($user)
            ->get(route('patients.index'))
            ->assertOk()
            ->assertSee($ownPatient->full_name)
            ->assertDontSee($otherPatient->full_name);
    }

    public function test_authenticated_user_can_open_create_patient_form(): void
    {
        $user = $this->userForClinic(Clinic::factory()->create());

        $this->actingAs($user)
            ->get(route('patients.create'))
            ->assertOk()
            ->assertSee('Nuevo paciente');
    }

    public function test_authenticated_user_can_create_valid_patient(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);

        $response = $this->actingAs($user)
            ->post(route('patients.store'), $this->validPatientPayload([
                'first_name' => 'Maria',
                'last_name' => 'Alvarez',
                'identification_number' => 'PAT-1001',
            ]));

        $response
            ->assertRedirect(route('patients.index'))
            ->assertSessionHas('success', 'Paciente creado correctamente.');

        $this->assertDatabaseHas('patients', [
            'clinic_id' => $clinic->id,
            'first_name' => 'Maria',
            'last_name' => 'Alvarez',
            'identification_number' => 'PAT-1001',
        ]);
    }

    public function test_patient_cannot_be_created_without_first_name(): void
    {
        $user = $this->userForClinic(Clinic::factory()->create());

        $this->actingAs($user)
            ->from(route('patients.create'))
            ->post(route('patients.store'), $this->validPatientPayload(['first_name' => '']))
            ->assertRedirect(route('patients.create'))
            ->assertSessionHasErrors('first_name');
    }

    public function test_patient_cannot_be_created_without_last_name(): void
    {
        $user = $this->userForClinic(Clinic::factory()->create());

        $this->actingAs($user)
            ->from(route('patients.create'))
            ->post(route('patients.store'), $this->validPatientPayload(['last_name' => '']))
            ->assertRedirect(route('patients.create'))
            ->assertSessionHasErrors('last_name');
    }

    public function test_patient_identification_number_must_be_unique(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        Patient::factory()->for($clinic)->create(['identification_number' => 'DUP-100']);

        $this->actingAs($user)
            ->from(route('patients.create'))
            ->post(route('patients.store'), $this->validPatientPayload(['identification_number' => 'DUP-100']))
            ->assertRedirect(route('patients.create'))
            ->assertSessionHasErrors('identification_number');
    }

    public function test_authenticated_user_can_view_patient_from_own_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $patient = Patient::factory()->for($clinic)->create();

        $this->actingAs($user)
            ->get(route('patients.show', $patient))
            ->assertOk()
            ->assertSee($patient->full_name);
    }

    public function test_authenticated_user_can_open_edit_form_for_own_patient(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $patient = Patient::factory()->for($clinic)->create();

        $this->actingAs($user)
            ->get(route('patients.edit', $patient))
            ->assertOk()
            ->assertSee('Editar paciente')
            ->assertSee($patient->full_name);
    }

    public function test_authenticated_user_can_update_patient_from_own_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $patient = Patient::factory()->for($clinic)->create(['identification_number' => 'UPD-100']);

        $response = $this->actingAs($user)
            ->put(route('patients.update', $patient), $this->validPatientPayload([
                'first_name' => 'Updated',
                'last_name' => 'Patient',
                'identification_number' => 'UPD-100',
                'status' => 'inactive',
            ]));

        $response
            ->assertRedirect(route('patients.show', $patient))
            ->assertSessionHas('success', 'Paciente actualizado correctamente.');

        $this->assertDatabaseHas('patients', [
            'id' => $patient->id,
            'clinic_id' => $clinic->id,
            'first_name' => 'Updated',
            'last_name' => 'Patient',
            'status' => 'inactive',
        ]);
    }

    public function test_authenticated_user_can_delete_patient_from_own_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $patient = Patient::factory()->for($clinic)->create();

        $this->actingAs($user)
            ->delete(route('patients.destroy', $patient))
            ->assertRedirect(route('patients.index'))
            ->assertSessionHas('success', 'Paciente eliminado correctamente.');

        $this->assertDatabaseMissing('patients', [
            'id' => $patient->id,
        ]);
    }

    public function test_search_by_patient_name_works(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $matchingPatient = Patient::factory()->for($clinic)->create(['first_name' => 'Isabella', 'last_name' => 'Vera']);
        $otherPatient = Patient::factory()->for($clinic)->create(['first_name' => 'Roberto', 'last_name' => 'Salas']);

        $this->actingAs($user)
            ->get(route('patients.index', ['search' => 'Isabella']))
            ->assertOk()
            ->assertSee($matchingPatient->full_name)
            ->assertDontSee($otherPatient->full_name);
    }

    public function test_search_by_identification_number_works(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $matchingPatient = Patient::factory()->for($clinic)->create(['identification_number' => 'ID-SEARCH-1']);
        $otherPatient = Patient::factory()->for($clinic)->create(['identification_number' => 'ID-SEARCH-2']);

        $this->actingAs($user)
            ->get(route('patients.index', ['search' => 'ID-SEARCH-1']))
            ->assertOk()
            ->assertSee($matchingPatient->identification_number)
            ->assertDontSee($otherPatient->identification_number);
    }

    public function test_filter_by_active_status_works(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $activePatient = Patient::factory()->for($clinic)->create([
            'first_name' => 'Activo',
            'last_name' => 'Visible',
            'status' => 'active',
        ]);
        $inactivePatient = Patient::factory()->for($clinic)->create([
            'first_name' => 'Inactivo',
            'last_name' => 'Oculto',
            'status' => 'inactive',
        ]);

        $this->actingAs($user)
            ->get(route('patients.index', ['status' => 'active']))
            ->assertOk()
            ->assertSee($activePatient->full_name)
            ->assertDontSee($inactivePatient->full_name);
    }

    public function test_filter_by_inactive_status_works(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $activePatient = Patient::factory()->for($clinic)->create([
            'first_name' => 'Activo',
            'last_name' => 'Oculto',
            'status' => 'active',
        ]);
        $inactivePatient = Patient::factory()->for($clinic)->create([
            'first_name' => 'Inactivo',
            'last_name' => 'Visible',
            'status' => 'inactive',
        ]);

        $this->actingAs($user)
            ->get(route('patients.index', ['status' => 'inactive']))
            ->assertOk()
            ->assertSee($inactivePatient->full_name)
            ->assertDontSee($activePatient->full_name);
    }

    public function test_user_cannot_view_patient_from_another_clinic(): void
    {
        [$user, $otherPatient] = $this->userAndOtherClinicPatient();

        $this->actingAs($user)
            ->get(route('patients.show', $otherPatient))
            ->assertForbidden();
    }

    public function test_user_cannot_edit_patient_from_another_clinic(): void
    {
        [$user, $otherPatient] = $this->userAndOtherClinicPatient();

        $this->actingAs($user)
            ->get(route('patients.edit', $otherPatient))
            ->assertForbidden();
    }

    public function test_user_cannot_update_patient_from_another_clinic(): void
    {
        [$user, $otherPatient] = $this->userAndOtherClinicPatient();

        $this->actingAs($user)
            ->put(route('patients.update', $otherPatient), $this->validPatientPayload([
                'identification_number' => $otherPatient->identification_number,
            ]))
            ->assertForbidden();
    }

    public function test_user_cannot_delete_patient_from_another_clinic(): void
    {
        [$user, $otherPatient] = $this->userAndOtherClinicPatient();

        $this->actingAs($user)
            ->delete(route('patients.destroy', $otherPatient))
            ->assertForbidden();

        $this->assertDatabaseHas('patients', [
            'id' => $otherPatient->id,
        ]);
    }

    public function test_patient_blood_type_accepts_allowed_values(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);

        foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bloodType) {
            $this->actingAs($user)
                ->post(route('patients.store'), $this->validPatientPayload([
                    'identification_number' => 'BT-'.str_replace(['+', '-'], ['P', 'N'], $bloodType),
                    'email' => strtolower(str_replace(['+', '-'], ['p', 'n'], $bloodType)).'@example.test',
                    'blood_type' => $bloodType,
                ]))
                ->assertRedirect(route('patients.index'));

            $this->assertDatabaseHas('patients', [
                'clinic_id' => $clinic->id,
                'blood_type' => $bloodType,
            ]);
        }
    }

    public function test_patient_blood_type_rejects_invalid_values(): void
    {
        $user = $this->userForClinic(Clinic::factory()->create());

        $this->actingAs($user)
            ->from(route('patients.create'))
            ->post(route('patients.store'), $this->validPatientPayload(['blood_type' => 'X+']))
            ->assertRedirect(route('patients.create'))
            ->assertSessionHasErrors('blood_type');
    }

    private function userForClinic(Clinic $clinic): User
    {
        return User::factory()->create([
            'clinic_id' => $clinic->id,
        ]);
    }

    /**
     * @return array{0: User, 1: Patient}
     */
    private function userAndOtherClinicPatient(): array
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();

        return [
            $this->userForClinic($clinic),
            Patient::factory()->for($otherClinic)->create(),
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPatientPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'identification_number' => 'ID-'.fake()->unique()->numerify('######'),
            'birth_date' => '1990-01-15',
            'gender' => 'otro',
            'phone' => '0991234567',
            'email' => fake()->unique()->safeEmail(),
            'address' => 'Guayaquil, Ecuador',
            'blood_type' => 'O+',
            'allergies' => 'Sin alergias conocidas',
            'emergency_contact_name' => 'Contacto Emergencia',
            'emergency_contact_phone' => '0997654321',
            'status' => 'active',
        ], $overrides);
    }
}

