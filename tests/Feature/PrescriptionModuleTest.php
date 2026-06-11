<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrescriptionModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_prescriptions_index(): void
    {
        $this->get(route('prescriptions.index'))->assertRedirect(route('login', absolute: false));
    }

    public function test_authenticated_user_can_see_prescriptions_from_own_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $ownPrescription = $this->prescriptionForClinic($clinic, medication: 'Medicamento visible');
        $otherPrescription = $this->prescriptionForClinic($otherClinic, medication: 'Medicamento oculto');

        $this->actingAs($user)
            ->get(route('prescriptions.index'))
            ->assertOk()
            ->assertSee('Medicamento visible')
            ->assertDontSee('Medicamento oculto');
    }

    public function test_authenticated_user_can_open_create_prescription_form(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $this->patientForClinic($clinic);
        $this->doctorForClinic($clinic);
        $this->consultationForClinic($clinic);

        $this->actingAs($user)
            ->get(route('prescriptions.create'))
            ->assertOk()
            ->assertSee('Nueva receta');
    }

    public function test_authenticated_user_can_create_valid_prescription_without_consultation(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        [$patient, $doctor] = $this->relatedRecords($clinic);

        $this->actingAs($user)
            ->post(route('prescriptions.store'), $this->validPayload($patient, $doctor, null, ['general_instructions' => 'Tomar con alimentos']))
            ->assertRedirect(route('prescriptions.index'))
            ->assertSessionHas('success', 'Receta creada correctamente.');

        $this->assertDatabaseHas('prescriptions', [
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'consultation_id' => null,
            'general_instructions' => 'Tomar con alimentos',
            'status' => 'active',
        ]);
    }

    public function test_authenticated_user_can_create_valid_prescription_with_consultation(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        [$patient, $doctor] = $this->relatedRecords($clinic);
        $consultation = $this->consultationForClinic($clinic, $patient, $doctor);

        $this->actingAs($user)
            ->post(route('prescriptions.store'), $this->validPayload($patient, $doctor, $consultation))
            ->assertRedirect(route('prescriptions.index'));

        $this->assertDatabaseHas('prescriptions', [
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'consultation_id' => $consultation->id,
        ]);
    }

    public function test_creating_prescription_creates_prescription_items(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        [$patient, $doctor] = $this->relatedRecords($clinic);

        $this->actingAs($user)
            ->post(route('prescriptions.store'), $this->validPayload($patient, $doctor, null, [
                'items' => [
                    ['medication_name' => 'Paracetamol', 'dosage' => '500 mg', 'frequency' => 'Cada 8 horas', 'duration' => '3 días', 'instructions' => 'Después de comer'],
                    ['medication_name' => 'Ibuprofeno', 'dosage' => '400 mg', 'frequency' => 'Cada 12 horas', 'duration' => '2 días', 'instructions' => 'Si hay dolor'],
                ],
            ]))
            ->assertRedirect(route('prescriptions.index'));

        $this->assertDatabaseHas('prescription_items', ['medication_name' => 'Paracetamol']);
        $this->assertDatabaseHas('prescription_items', ['medication_name' => 'Ibuprofeno']);
    }

    public function test_prescription_cannot_be_created_without_patient_id(): void
    {
        [$user, $patient, $doctor] = $this->setupForValidation();

        $this->actingAs($user)
            ->from(route('prescriptions.create'))
            ->post(route('prescriptions.store'), $this->validPayload($patient, $doctor, null, ['patient_id' => '']))
            ->assertRedirect(route('prescriptions.create'))
            ->assertSessionHasErrors('patient_id');
    }

    public function test_prescription_cannot_be_created_without_doctor_id(): void
    {
        [$user, $patient, $doctor] = $this->setupForValidation();

        $this->actingAs($user)
            ->from(route('prescriptions.create'))
            ->post(route('prescriptions.store'), $this->validPayload($patient, $doctor, null, ['doctor_id' => '']))
            ->assertRedirect(route('prescriptions.create'))
            ->assertSessionHasErrors('doctor_id');
    }

    public function test_prescription_cannot_be_created_without_prescription_date(): void
    {
        [$user, $patient, $doctor] = $this->setupForValidation();

        $this->actingAs($user)
            ->from(route('prescriptions.create'))
            ->post(route('prescriptions.store'), $this->validPayload($patient, $doctor, null, ['prescription_date' => '']))
            ->assertRedirect(route('prescriptions.create'))
            ->assertSessionHasErrors('prescription_date');
    }

    public function test_prescription_cannot_be_created_without_items(): void
    {
        [$user, $patient, $doctor] = $this->setupForValidation();

        $this->actingAs($user)
            ->from(route('prescriptions.create'))
            ->post(route('prescriptions.store'), $this->validPayload($patient, $doctor, null, ['items' => []]))
            ->assertRedirect(route('prescriptions.create'))
            ->assertSessionHasErrors('items');
    }

    public function test_prescription_cannot_be_created_with_item_without_medication_name(): void
    {
        [$user, $patient, $doctor] = $this->setupForValidation();

        $this->actingAs($user)
            ->from(route('prescriptions.create'))
            ->post(route('prescriptions.store'), $this->validPayload($patient, $doctor, null, [
                'items' => [['medication_name' => '', 'dosage' => '500 mg', 'frequency' => 'Cada 8 horas', 'duration' => '3 días', 'instructions' => 'Con comida']],
            ]))
            ->assertRedirect(route('prescriptions.create'))
            ->assertSessionHasErrors('items.0.medication_name');
    }

    public function test_prescription_cannot_be_created_with_patient_from_other_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        [$patient, $doctor] = $this->relatedRecords($clinic);
        $otherPatient = $this->patientForClinic($otherClinic);

        $this->actingAs($user)
            ->from(route('prescriptions.create'))
            ->post(route('prescriptions.store'), $this->validPayload($patient, $doctor, null, ['patient_id' => $otherPatient->id]))
            ->assertRedirect(route('prescriptions.create'))
            ->assertSessionHasErrors('clinic_id');
    }

    public function test_prescription_cannot_be_created_with_doctor_from_other_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        [$patient, $doctor] = $this->relatedRecords($clinic);
        $otherDoctor = $this->doctorForClinic($otherClinic);

        $this->actingAs($user)
            ->from(route('prescriptions.create'))
            ->post(route('prescriptions.store'), $this->validPayload($patient, $doctor, null, ['doctor_id' => $otherDoctor->id]))
            ->assertRedirect(route('prescriptions.create'))
            ->assertSessionHasErrors('clinic_id');
    }

    public function test_prescription_cannot_be_created_with_consultation_from_other_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        [$patient, $doctor] = $this->relatedRecords($clinic);
        $otherConsultation = $this->consultationForClinic($otherClinic);

        $this->actingAs($user)
            ->from(route('prescriptions.create'))
            ->post(route('prescriptions.store'), $this->validPayload($patient, $doctor, $otherConsultation))
            ->assertRedirect(route('prescriptions.create'))
            ->assertSessionHasErrors('clinic_id');
    }

    public function test_prescription_cannot_be_created_when_consultation_does_not_match_patient_and_doctor(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        [$patient, $doctor] = $this->relatedRecords($clinic);
        $otherPatient = $this->patientForClinic($clinic, 'Paciente Diferente');
        $consultation = $this->consultationForClinic($clinic, $otherPatient, $doctor);

        $this->actingAs($user)
            ->from(route('prescriptions.create'))
            ->post(route('prescriptions.store'), $this->validPayload($patient, $doctor, $consultation))
            ->assertRedirect(route('prescriptions.create'))
            ->assertSessionHasErrors('consultation_id');
    }

    public function test_authenticated_user_can_view_prescription_from_own_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $prescription = $this->prescriptionForClinic($clinic, instructions: 'Vista receta');

        $this->actingAs($user)->get(route('prescriptions.show', $prescription))->assertOk()->assertSee('Vista receta');
    }

    public function test_authenticated_user_can_edit_prescription_from_own_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $prescription = $this->prescriptionForClinic($clinic);

        $this->actingAs($user)->get(route('prescriptions.edit', $prescription))->assertOk()->assertSee('Editar receta');
    }

    public function test_authenticated_user_can_update_prescription_from_own_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        [$patient, $doctor] = $this->relatedRecords($clinic);
        $prescription = $this->prescriptionForClinic($clinic, $patient, $doctor);

        $this->actingAs($user)
            ->put(route('prescriptions.update', $prescription), $this->validPayload($patient, $doctor, null, ['general_instructions' => 'Instrucciones actualizadas']))
            ->assertRedirect(route('prescriptions.show', $prescription))
            ->assertSessionHas('success', 'Receta actualizada correctamente.');

        $this->assertDatabaseHas('prescriptions', ['id' => $prescription->id, 'general_instructions' => 'Instrucciones actualizadas']);
    }

    public function test_updating_prescription_replaces_items(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        [$patient, $doctor] = $this->relatedRecords($clinic);
        $prescription = $this->prescriptionForClinic($clinic, $patient, $doctor, medication: 'Medicamento anterior');
        $oldItemId = $prescription->items()->first()->id;

        $this->actingAs($user)
            ->put(route('prescriptions.update', $prescription), $this->validPayload($patient, $doctor, null, [
                'items' => [
                    ['medication_name' => 'Medicamento nuevo', 'dosage' => '10 mg', 'frequency' => 'Diario', 'duration' => '7 días', 'instructions' => 'En la mañana'],
                    ['medication_name' => 'Segundo medicamento', 'dosage' => '5 mg', 'frequency' => 'Noche', 'duration' => '3 días', 'instructions' => 'Antes de dormir'],
                ],
            ]))
            ->assertRedirect(route('prescriptions.show', $prescription));

        $this->assertDatabaseMissing('prescription_items', ['id' => $oldItemId]);
        $this->assertDatabaseHas('prescription_items', ['prescription_id' => $prescription->id, 'medication_name' => 'Medicamento nuevo']);
        $this->assertDatabaseHas('prescription_items', ['prescription_id' => $prescription->id, 'medication_name' => 'Segundo medicamento']);
    }

    public function test_authenticated_user_can_delete_prescription_from_own_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $prescription = $this->prescriptionForClinic($clinic);

        $this->actingAs($user)
            ->delete(route('prescriptions.destroy', $prescription))
            ->assertRedirect(route('prescriptions.index'))
            ->assertSessionHas('success', 'Receta eliminada correctamente.');

        $this->assertDatabaseMissing('prescriptions', ['id' => $prescription->id]);
    }

    public function test_deleting_prescription_deletes_items_by_cascade(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $prescription = $this->prescriptionForClinic($clinic);
        $itemId = $prescription->items()->first()->id;

        $this->actingAs($user)->delete(route('prescriptions.destroy', $prescription));

        $this->assertDatabaseMissing('prescription_items', ['id' => $itemId]);
    }

    public function test_search_by_patient_works(): void
    {
        [$user, $match, $other] = $this->twoPrescriptions('Paciente Buscable', 'Paciente Oculto');

        $this->actingAs($user)->get(route('prescriptions.index', ['search' => 'Buscable']))->assertOk()->assertSee('Paciente Buscable')->assertDontSee('Paciente Oculto');
    }

    public function test_search_by_doctor_works(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $match = $this->prescriptionForClinic($clinic, doctor: $this->doctorForClinic($clinic, 'Doctor Receta'), medication: 'Doctor visible');
        $other = $this->prescriptionForClinic($clinic, doctor: $this->doctorForClinic($clinic, 'Doctor Otro'), medication: 'Doctor oculto');

        $this->actingAs($user)->get(route('prescriptions.index', ['search' => 'Receta']))->assertOk()->assertSee('Doctor visible')->assertDontSee('Doctor oculto');
    }

    public function test_search_by_medication_works(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $match = $this->prescriptionForClinic($clinic, medication: 'Azitromicina');
        $other = $this->prescriptionForClinic($clinic, medication: 'Loratadina');

        $this->actingAs($user)->get(route('prescriptions.index', ['search' => 'Azitromicina']))->assertOk()->assertSee('Azitromicina')->assertDontSee('Loratadina');
    }

    public function test_filter_by_doctor_id_works(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $doctor = $this->doctorForClinic($clinic, 'Doctor Filtro');
        $match = $this->prescriptionForClinic($clinic, doctor: $doctor, medication: 'Doctor filtrado');
        $other = $this->prescriptionForClinic($clinic, doctor: $this->doctorForClinic($clinic, 'Otro Filtro'), medication: 'Doctor no filtrado');

        $this->actingAs($user)->get(route('prescriptions.index', ['doctor_id' => $doctor->id]))->assertOk()->assertSee('Doctor filtrado')->assertDontSee('Doctor no filtrado');
    }

    public function test_filter_by_active_status_works(): void
    {
        [$user, $match, $other] = $this->twoPrescriptionsByStatus('active', 'cancelled');

        $this->actingAs($user)->get(route('prescriptions.index', ['status' => 'active']))->assertOk()->assertSee('Estado visible')->assertDontSee('Estado oculto');
    }

    public function test_filter_by_cancelled_status_works(): void
    {
        [$user, $match, $other] = $this->twoPrescriptionsByStatus('cancelled', 'active');

        $this->actingAs($user)->get(route('prescriptions.index', ['status' => 'cancelled']))->assertOk()->assertSee('Estado visible')->assertDontSee('Estado oculto');
    }

    public function test_filter_by_date_works(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $match = $this->prescriptionForClinic($clinic, date: '2026-08-10', medication: 'Fecha filtrada');
        $other = $this->prescriptionForClinic($clinic, date: '2026-08-11', medication: 'Fecha no filtrada');

        $this->actingAs($user)->get(route('prescriptions.index', ['date' => '2026-08-10']))->assertOk()->assertSee('Fecha filtrada')->assertDontSee('Fecha no filtrada');
    }

    public function test_user_cannot_view_prescription_from_other_clinic(): void
    {
        [$user, $prescription] = $this->userAndOtherClinicPrescription();
        $this->actingAs($user)->get(route('prescriptions.show', $prescription))->assertForbidden();
    }

    public function test_user_cannot_edit_prescription_from_other_clinic(): void
    {
        [$user, $prescription] = $this->userAndOtherClinicPrescription();
        $this->actingAs($user)->get(route('prescriptions.edit', $prescription))->assertForbidden();
    }

    public function test_user_cannot_update_prescription_from_other_clinic(): void
    {
        [$user, $prescription] = $this->userAndOtherClinicPrescription();

        $this->actingAs($user)
            ->put(route('prescriptions.update', $prescription), $this->validPayload($prescription->patient, $prescription->doctor, null))
            ->assertForbidden();
    }

    public function test_user_cannot_delete_prescription_from_other_clinic(): void
    {
        [$user, $prescription] = $this->userAndOtherClinicPrescription();

        $this->actingAs($user)->delete(route('prescriptions.destroy', $prescription))->assertForbidden();
        $this->assertDatabaseHas('prescriptions', ['id' => $prescription->id]);
    }

    private function userForClinic(Clinic $clinic): User
    {
        return User::factory()->create(['clinic_id' => $clinic->id]);
    }

    private function patientForClinic(Clinic $clinic, string $name = 'Paciente Test'): Patient
    {
        [$first, $last] = array_pad(explode(' ', $name, 2), 2, 'Test');
        return Patient::factory()->for($clinic)->create(['first_name' => $first, 'last_name' => $last]);
    }

    private function doctorForClinic(Clinic $clinic, string $name = 'Doctor Test'): Doctor
    {
        $user = User::factory()->create(['clinic_id' => $clinic->id, 'name' => $name]);
        return Doctor::factory()->for($clinic)->for($user)->create(['specialty_id' => Specialty::factory()->create()->id]);
    }

    private function relatedRecords(Clinic $clinic): array
    {
        return [$this->patientForClinic($clinic), $this->doctorForClinic($clinic)];
    }

    private function consultationForClinic(Clinic $clinic, ?Patient $patient = null, ?Doctor $doctor = null): Consultation
    {
        $patient ??= $this->patientForClinic($clinic);
        $doctor ??= $this->doctorForClinic($clinic);

        return Consultation::factory()->for($patient)->for($doctor)->create([
            'reason' => 'Consulta para receta',
            'diagnosis' => 'Diagnóstico base',
            'consultation_date' => '2026-07-10 09:00:00',
        ]);
    }

    private function prescriptionForClinic(Clinic $clinic, ?Patient $patient = null, ?Doctor $doctor = null, ?Consultation $consultation = null, string $status = 'active', string $date = '2026-07-10', string $instructions = 'Indicaciones de prueba', string $medication = 'Paracetamol'): Prescription
    {
        $patient ??= $this->patientForClinic($clinic);
        $doctor ??= $this->doctorForClinic($clinic);

        $prescription = Prescription::factory()->for($patient)->for($doctor)->create([
            'consultation_id' => $consultation?->id,
            'prescription_date' => $date,
            'general_instructions' => $instructions,
            'status' => $status,
        ]);

        PrescriptionItem::factory()->for($prescription)->create(['medication_name' => $medication]);

        return $prescription;
    }

    private function validPayload(Patient $patient, Doctor $doctor, ?Consultation $consultation, array $overrides = []): array
    {
        return array_merge([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'consultation_id' => $consultation?->id,
            'prescription_date' => '2026-07-15',
            'general_instructions' => 'Tomar abundante agua',
            'status' => 'active',
            'items' => [
                ['medication_name' => 'Paracetamol', 'dosage' => '500 mg', 'frequency' => 'Cada 8 horas', 'duration' => '3 días', 'instructions' => 'Después de comer'],
            ],
        ], $overrides);
    }

    private function setupForValidation(): array
    {
        $clinic = Clinic::factory()->create();
        return [$this->userForClinic($clinic), ...$this->relatedRecords($clinic)];
    }

    private function twoPrescriptions(string $matchingPatient, string $otherPatient): array
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $match = $this->prescriptionForClinic($clinic, patient: $this->patientForClinic($clinic, $matchingPatient), medication: 'Receta visible');
        $other = $this->prescriptionForClinic($clinic, patient: $this->patientForClinic($clinic, $otherPatient), medication: 'Receta oculta');
        return [$user, $match, $other];
    }

    private function twoPrescriptionsByStatus(string $matchingStatus, string $otherStatus): array
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $match = $this->prescriptionForClinic($clinic, status: $matchingStatus, medication: 'Estado visible');
        $other = $this->prescriptionForClinic($clinic, status: $otherStatus, medication: 'Estado oculto');
        return [$user, $match, $other];
    }

    private function userAndOtherClinicPrescription(): array
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        return [$this->userForClinic($clinic), $this->prescriptionForClinic($otherClinic)];
    }
}
