<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Service;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicalRecordModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_medical_records_index(): void
    {
        $this->get(route('medical-records.index'))->assertRedirect(route('login', absolute: false));
    }

    public function test_authenticated_user_can_see_medical_records_from_own_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $ownRecord = $this->medicalRecordForClinic($clinic, patientName: 'Paciente Visible', chronicDiseases: 'Diabetes visible');
        $otherRecord = $this->medicalRecordForClinic($otherClinic, patientName: 'Paciente Oculto', chronicDiseases: 'Hipertension oculta');

        $this->actingAs($user)
            ->get(route('medical-records.index'))
            ->assertOk()
            ->assertSee($ownRecord->patient->full_name)
            ->assertDontSee($otherRecord->patient->full_name);
    }

    public function test_authenticated_user_can_open_create_medical_record_form(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $patient = $this->patientForClinic($clinic, 'Paciente Disponible');

        $this->actingAs($user)
            ->get(route('medical-records.create'))
            ->assertOk()
            ->assertSee('Nuevo historial')
            ->assertSee($patient->full_name);
    }

    public function test_authenticated_user_can_create_valid_medical_record(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $patient = $this->patientForClinic($clinic, 'Paciente Historial');

        $this->actingAs($user)
            ->post(route('medical-records.store'), $this->validPayload($patient, ['chronic_diseases' => 'Asma controlada']))
            ->assertRedirect()
            ->assertSessionHas('success', 'Historial clinico creado correctamente.');

        $this->assertDatabaseHas('medical_records', [
            'patient_id' => $patient->id,
            'chronic_diseases' => 'Asma controlada',
        ]);
    }

    public function test_medical_record_cannot_be_created_without_patient_id(): void
    {
        [$user, $patient] = $this->setupForValidation();

        $this->actingAs($user)
            ->from(route('medical-records.create'))
            ->post(route('medical-records.store'), $this->validPayload($patient, ['patient_id' => '']))
            ->assertRedirect(route('medical-records.create'))
            ->assertSessionHasErrors('patient_id');
    }

    public function test_medical_record_cannot_be_created_with_patient_from_other_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $patient = $this->patientForClinic($clinic);
        $otherPatient = $this->patientForClinic($otherClinic);

        $this->actingAs($user)
            ->from(route('medical-records.create'))
            ->post(route('medical-records.store'), $this->validPayload($patient, ['patient_id' => $otherPatient->id]))
            ->assertRedirect(route('medical-records.create'))
            ->assertSessionHasErrors('clinic_id');
    }

    public function test_patient_cannot_have_more_than_one_medical_record(): void
    {
        [$user, $patient] = $this->setupForValidation();
        MedicalRecord::factory()->for($patient)->create();

        $this->actingAs($user)
            ->from(route('medical-records.create'))
            ->post(route('medical-records.store'), $this->validPayload($patient))
            ->assertRedirect(route('medical-records.create'))
            ->assertSessionHasErrors('patient_id');
    }

    public function test_authenticated_user_can_view_medical_record_from_own_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $record = $this->medicalRecordForClinic($clinic, personalHistory: 'Antecedente visible');

        $this->actingAs($user)
            ->get(route('medical-records.show', $record))
            ->assertOk()
            ->assertSee('Antecedente visible');
    }

    public function test_authenticated_user_can_edit_medical_record_from_own_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $record = $this->medicalRecordForClinic($clinic);

        $this->actingAs($user)
            ->get(route('medical-records.edit', $record))
            ->assertOk()
            ->assertSee('Editar historial');
    }

    public function test_authenticated_user_can_update_medical_record_from_own_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $record = $this->medicalRecordForClinic($clinic);

        $this->actingAs($user)
            ->put(route('medical-records.update', $record), $this->validPayload($record->patient, ['observations' => 'Observacion actualizada']))
            ->assertRedirect(route('medical-records.show', $record))
            ->assertSessionHas('success', 'Historial clinico actualizado correctamente.');

        $this->assertDatabaseHas('medical_records', [
            'id' => $record->id,
            'observations' => 'Observacion actualizada',
        ]);
    }

    public function test_authenticated_user_can_delete_medical_record_from_own_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $record = $this->medicalRecordForClinic($clinic);

        $this->actingAs($user)
            ->delete(route('medical-records.destroy', $record))
            ->assertRedirect(route('medical-records.index'))
            ->assertSessionHas('success', 'Historial clinico eliminado correctamente.');

        $this->assertDatabaseMissing('medical_records', ['id' => $record->id]);
    }

    public function test_deleting_medical_record_does_not_delete_patient(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $record = $this->medicalRecordForClinic($clinic);
        $patientId = $record->patient_id;

        $this->actingAs($user)->delete(route('medical-records.destroy', $record));

        $this->assertDatabaseHas('patients', ['id' => $patientId]);
    }

    public function test_search_by_patient_name_works(): void
    {
        $this->assertSearchFinds('Paciente Buscado', 'Paciente Oculto', 'Paciente Buscado');
    }

    public function test_search_by_identification_works(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $match = $this->medicalRecordForClinic($clinic, patientName: 'Paciente Cedula Uno', identification: 'QA-IDENT-001');
        $other = $this->medicalRecordForClinic($clinic, patientName: 'Paciente Cedula Dos', identification: 'QA-IDENT-002');

        $this->actingAs($user)
            ->get(route('medical-records.index', ['search' => 'QA-IDENT-001']))
            ->assertOk()
            ->assertSee($match->patient->full_name)
            ->assertDontSee($other->patient->full_name);
    }

    public function test_search_by_chronic_diseases_works(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $match = $this->medicalRecordForClinic($clinic, patientName: 'Paciente Cronico Uno', chronicDiseases: 'Hipotiroidismo QA');
        $other = $this->medicalRecordForClinic($clinic, patientName: 'Paciente Cronico Dos', chronicDiseases: 'Asma leve');

        $this->actingAs($user)
            ->get(route('medical-records.index', ['search' => 'Hipotiroidismo QA']))
            ->assertOk()
            ->assertSee($match->patient->full_name)
            ->assertDontSee($other->patient->full_name);
    }

    public function test_user_cannot_view_medical_record_from_other_clinic(): void
    {
        [$user, $record] = $this->otherClinicRecord();

        $this->actingAs($user)->get(route('medical-records.show', $record))->assertForbidden();
    }

    public function test_user_cannot_edit_medical_record_from_other_clinic(): void
    {
        [$user, $record] = $this->otherClinicRecord();

        $this->actingAs($user)->get(route('medical-records.edit', $record))->assertForbidden();
    }

    public function test_user_cannot_update_medical_record_from_other_clinic(): void
    {
        [$user, $record] = $this->otherClinicRecord();

        $this->actingAs($user)
            ->put(route('medical-records.update', $record), $this->validPayload($record->patient))
            ->assertForbidden();
    }

    public function test_user_cannot_delete_medical_record_from_other_clinic(): void
    {
        [$user, $record] = $this->otherClinicRecord();

        $this->actingAs($user)->delete(route('medical-records.destroy', $record))->assertForbidden();
    }

    public function test_show_displays_recent_patient_consultations(): void
    {
        [$clinic, $user, $patient, $doctor] = $this->baseClinicData();
        $record = $this->medicalRecordForClinic($clinic, patient: $patient);
        Consultation::factory()
            ->for($patient)
            ->for($doctor)
            ->create(['diagnosis' => 'Diagnostico reciente QA', 'treatment' => 'Tratamiento reciente QA']);

        $this->actingAs($user)
            ->get(route('medical-records.show', $record))
            ->assertOk()
            ->assertSee('Diagnostico reciente QA')
            ->assertSee('Tratamiento reciente QA');
    }

    public function test_show_displays_recent_patient_prescriptions(): void
    {
        [$clinic, $user, $patient, $doctor] = $this->baseClinicData();
        $record = $this->medicalRecordForClinic($clinic, patient: $patient);
        $prescription = Prescription::factory()->for($patient)->for($doctor)->create(['status' => 'active']);
        PrescriptionItem::factory()->for($prescription)->create(['medication_name' => 'Ibuprofeno QA']);

        $this->actingAs($user)
            ->get(route('medical-records.show', $record))
            ->assertOk()
            ->assertSee('Ibuprofeno QA')
            ->assertSee('Activa');
    }

    public function test_show_displays_recent_patient_appointments(): void
    {
        [$clinic, $user, $patient, $doctor] = $this->baseClinicData();
        $record = $this->medicalRecordForClinic($clinic, patient: $patient);
        Appointment::factory()
            ->for($clinic)
            ->for($patient)
            ->for($doctor)
            ->for(Service::factory()->for($clinic))
            ->create(['appointment_date' => '2026-09-15', 'start_time' => '09:30:00', 'status' => 'confirmed']);

        $this->actingAs($user)
            ->get(route('medical-records.show', $record))
            ->assertOk()
            ->assertSee('15/09/2026')
            ->assertSee('Confirmada');
    }

    private function userForClinic(Clinic $clinic): User
    {
        return User::factory()->create(['clinic_id' => $clinic->id]);
    }

    private function patientForClinic(Clinic $clinic, string $name = 'Paciente Test', ?string $identification = null): Patient
    {
        [$firstName, $lastName] = array_pad(explode(' ', $name, 2), 2, 'Clinico');

        return Patient::factory()->for($clinic)->create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'identification_number' => $identification ?? fake()->unique()->numerify('##########'),
        ]);
    }

    private function doctorForClinic(Clinic $clinic, string $name = 'Doctor Historial'): Doctor
    {
        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
            'name' => $name,
            'email' => fake()->unique()->safeEmail(),
        ]);

        return Doctor::factory()
            ->for($clinic)
            ->for($user)
            ->for(Specialty::factory())
            ->create();
    }

    private function medicalRecordForClinic(
        Clinic $clinic,
        ?Patient $patient = null,
        string $patientName = 'Paciente Historial',
        ?string $identification = null,
        string $personalHistory = 'Antecedentes personales QA',
        string $chronicDiseases = 'Enfermedades cronicas QA',
    ): MedicalRecord {
        $patient ??= $this->patientForClinic($clinic, $patientName, $identification);

        return MedicalRecord::factory()->for($patient)->create([
            'personal_history' => $personalHistory,
            'chronic_diseases' => $chronicDiseases,
            'current_medications' => 'Medicamentos actuales QA',
        ]);
    }

    private function validPayload(Patient $patient, array $overrides = []): array
    {
        return array_merge([
            'patient_id' => $patient->id,
            'personal_history' => 'Antecedente personal de prueba',
            'family_history' => 'Antecedente familiar de prueba',
            'surgical_history' => 'Cirugia previa de prueba',
            'current_medications' => 'Medicamento actual de prueba',
            'chronic_diseases' => 'Enfermedad cronica de prueba',
            'observations' => 'Observacion general de prueba',
        ], $overrides);
    }

    private function setupForValidation(): array
    {
        $clinic = Clinic::factory()->create();

        return [$this->userForClinic($clinic), $this->patientForClinic($clinic)];
    }

    private function assertSearchFinds(string $matchingPatient, string $otherPatient, string $search): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $match = $this->medicalRecordForClinic($clinic, patient: $this->patientForClinic($clinic, $matchingPatient));
        $other = $this->medicalRecordForClinic($clinic, patient: $this->patientForClinic($clinic, $otherPatient));

        $this->actingAs($user)
            ->get(route('medical-records.index', ['search' => $search]))
            ->assertOk()
            ->assertSee($match->patient->full_name)
            ->assertDontSee($other->patient->full_name);
    }

    private function otherClinicRecord(): array
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();

        return [$this->userForClinic($clinic), $this->medicalRecordForClinic($otherClinic)];
    }

    private function baseClinicData(): array
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $patient = $this->patientForClinic($clinic, 'Paciente Relacionado');
        $doctor = $this->doctorForClinic($clinic, 'Doctor Relacionado');

        return [$clinic, $user, $patient, $doctor];
    }
}
