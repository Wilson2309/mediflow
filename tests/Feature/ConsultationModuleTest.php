<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsultationModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_consultations_index(): void
    {
        $this->get(route('consultations.index'))->assertRedirect(route('login', absolute: false));
    }

    public function test_authenticated_user_can_see_consultations_from_own_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $ownConsultation = $this->consultationForClinic($clinic, diagnosis: 'Diagnóstico visible');
        $otherConsultation = $this->consultationForClinic($otherClinic, diagnosis: 'Diagnóstico oculto');

        $this->actingAs($user)
            ->get(route('consultations.index'))
            ->assertOk()
            ->assertSee($ownConsultation->diagnosis)
            ->assertDontSee($otherConsultation->diagnosis);
    }

    public function test_authenticated_user_can_open_create_consultation_form(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $this->patientForClinic($clinic);
        $this->doctorForClinic($clinic);
        $this->appointmentForClinic($clinic);

        $this->actingAs($user)
            ->get(route('consultations.create'))
            ->assertOk()
            ->assertSee('Nueva consulta');
    }

    public function test_authenticated_user_can_create_valid_consultation_without_appointment(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        [$patient, $doctor] = $this->relatedRecords($clinic);

        $this->actingAs($user)
            ->post(route('consultations.store'), $this->validPayload($patient, $doctor, null, ['diagnosis' => 'Rinitis aguda']))
            ->assertRedirect(route('consultations.index'))
            ->assertSessionHas('success', 'Consulta creada correctamente.');

        $this->assertDatabaseHas('consultations', [
            'appointment_id' => null,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'diagnosis' => 'Rinitis aguda',
        ]);
    }

    public function test_authenticated_user_can_create_valid_consultation_with_appointment(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        [$patient, $doctor] = $this->relatedRecords($clinic);
        $appointment = $this->appointmentForClinic($clinic, $patient, $doctor);

        $this->actingAs($user)
            ->post(route('consultations.store'), $this->validPayload($patient, $doctor, $appointment, ['diagnosis' => 'Control completo']))
            ->assertRedirect(route('consultations.index'));

        $this->assertDatabaseHas('consultations', [
            'appointment_id' => $appointment->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'diagnosis' => 'Control completo',
        ]);
    }

    public function test_creating_consultation_for_scheduled_or_confirmed_appointment_marks_it_completed(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        [$patient, $doctor] = $this->relatedRecords($clinic);
        $appointment = $this->appointmentForClinic($clinic, $patient, $doctor, 'confirmed');

        $this->actingAs($user)
            ->post(route('consultations.store'), $this->validPayload($patient, $doctor, $appointment))
            ->assertRedirect(route('consultations.index'));

        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'completed']);
    }

    public function test_consultation_cannot_be_created_without_patient_id(): void
    {
        [$user, $patient, $doctor] = $this->setupForValidation();

        $this->actingAs($user)
            ->from(route('consultations.create'))
            ->post(route('consultations.store'), $this->validPayload($patient, $doctor, null, ['patient_id' => '']))
            ->assertRedirect(route('consultations.create'))
            ->assertSessionHasErrors('patient_id');
    }

    public function test_consultation_cannot_be_created_without_doctor_id(): void
    {
        [$user, $patient, $doctor] = $this->setupForValidation();

        $this->actingAs($user)
            ->from(route('consultations.create'))
            ->post(route('consultations.store'), $this->validPayload($patient, $doctor, null, ['doctor_id' => '']))
            ->assertRedirect(route('consultations.create'))
            ->assertSessionHasErrors('doctor_id');
    }

    public function test_consultation_cannot_be_created_without_consultation_date(): void
    {
        [$user, $patient, $doctor] = $this->setupForValidation();

        $this->actingAs($user)
            ->from(route('consultations.create'))
            ->post(route('consultations.store'), $this->validPayload($patient, $doctor, null, ['consultation_date' => '']))
            ->assertRedirect(route('consultations.create'))
            ->assertSessionHasErrors('consultation_date');
    }

    public function test_consultation_cannot_be_created_with_patient_from_other_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        [$patient, $doctor] = $this->relatedRecords($clinic);
        $otherPatient = $this->patientForClinic($otherClinic);

        $this->actingAs($user)
            ->from(route('consultations.create'))
            ->post(route('consultations.store'), $this->validPayload($patient, $doctor, null, ['patient_id' => $otherPatient->id]))
            ->assertRedirect(route('consultations.create'))
            ->assertSessionHasErrors('clinic_id');
    }

    public function test_consultation_cannot_be_created_with_doctor_from_other_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        [$patient, $doctor] = $this->relatedRecords($clinic);
        $otherDoctor = $this->doctorForClinic($otherClinic);

        $this->actingAs($user)
            ->from(route('consultations.create'))
            ->post(route('consultations.store'), $this->validPayload($patient, $doctor, null, ['doctor_id' => $otherDoctor->id]))
            ->assertRedirect(route('consultations.create'))
            ->assertSessionHasErrors('clinic_id');
    }

    public function test_consultation_cannot_be_created_with_appointment_from_other_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        [$patient, $doctor] = $this->relatedRecords($clinic);
        $otherAppointment = $this->appointmentForClinic($otherClinic);

        $this->actingAs($user)
            ->from(route('consultations.create'))
            ->post(route('consultations.store'), $this->validPayload($patient, $doctor, $otherAppointment))
            ->assertRedirect(route('consultations.create'))
            ->assertSessionHasErrors('clinic_id');
    }

    public function test_consultation_cannot_be_created_for_cancelled_appointment(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        [$patient, $doctor] = $this->relatedRecords($clinic);
        $appointment = $this->appointmentForClinic($clinic, $patient, $doctor, 'cancelled');

        $this->actingAs($user)
            ->from(route('consultations.create'))
            ->post(route('consultations.store'), $this->validPayload($patient, $doctor, $appointment))
            ->assertRedirect(route('consultations.create'))
            ->assertSessionHasErrors('appointment_id');
    }

    public function test_consultation_cannot_be_created_for_no_show_appointment(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        [$patient, $doctor] = $this->relatedRecords($clinic);
        $appointment = $this->appointmentForClinic($clinic, $patient, $doctor, 'no_show');

        $this->actingAs($user)
            ->from(route('consultations.create'))
            ->post(route('consultations.store'), $this->validPayload($patient, $doctor, $appointment))
            ->assertRedirect(route('consultations.create'))
            ->assertSessionHasErrors('appointment_id');
    }

    public function test_cannot_create_more_than_one_consultation_for_same_appointment(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        [$patient, $doctor] = $this->relatedRecords($clinic);
        $appointment = $this->appointmentForClinic($clinic, $patient, $doctor, 'completed');
        $this->consultationForClinic($clinic, $patient, $doctor, $appointment);

        $this->actingAs($user)
            ->from(route('consultations.create'))
            ->post(route('consultations.store'), $this->validPayload($patient, $doctor, $appointment))
            ->assertRedirect(route('consultations.create'))
            ->assertSessionHasErrors('appointment_id');
    }

    public function test_authenticated_user_can_view_consultation_from_own_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $consultation = $this->consultationForClinic($clinic, diagnosis: 'Vista consulta');

        $this->actingAs($user)->get(route('consultations.show', $consultation))->assertOk()->assertSee('Vista consulta');
    }

    public function test_authenticated_user_can_edit_consultation_from_own_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $consultation = $this->consultationForClinic($clinic);

        $this->actingAs($user)->get(route('consultations.edit', $consultation))->assertOk()->assertSee('Editar consulta');
    }

    public function test_authenticated_user_can_update_consultation_from_own_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        [$patient, $doctor] = $this->relatedRecords($clinic);
        $consultation = $this->consultationForClinic($clinic, $patient, $doctor);

        $this->actingAs($user)
            ->put(route('consultations.update', $consultation), $this->validPayload($patient, $doctor, null, ['diagnosis' => 'Diagnóstico actualizado']))
            ->assertRedirect(route('consultations.show', $consultation))
            ->assertSessionHas('success', 'Consulta actualizada correctamente.');

        $this->assertDatabaseHas('consultations', ['id' => $consultation->id, 'diagnosis' => 'Diagnóstico actualizado']);
    }

    public function test_authenticated_user_can_delete_consultation_from_own_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $consultation = $this->consultationForClinic($clinic);

        $this->actingAs($user)
            ->delete(route('consultations.destroy', $consultation))
            ->assertRedirect(route('consultations.index'))
            ->assertSessionHas('success', 'Consulta eliminada correctamente.');

        $this->assertDatabaseMissing('consultations', ['id' => $consultation->id]);
    }

    public function test_search_by_patient_works(): void
    {
        [$user, $match, $other] = $this->twoConsultations('Paciente Buscable', 'Paciente Oculto');

        $this->actingAs($user)->get(route('consultations.index', ['search' => 'Buscable']))->assertOk()->assertSee($match->diagnosis)->assertDontSee($other->diagnosis);
    }

    public function test_search_by_doctor_works(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $match = $this->consultationForClinic($clinic, doctor: $this->doctorForClinic($clinic, 'Doctor Clinico'), diagnosis: 'Doctor visible');
        $other = $this->consultationForClinic($clinic, doctor: $this->doctorForClinic($clinic, 'Doctor Otro'), diagnosis: 'Doctor oculto');

        $this->actingAs($user)->get(route('consultations.index', ['search' => 'Clinico']))->assertOk()->assertSee($match->diagnosis)->assertDontSee($other->diagnosis);
    }

    public function test_search_by_diagnosis_works(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $match = $this->consultationForClinic($clinic, diagnosis: 'Gripe estacional');
        $other = $this->consultationForClinic($clinic, diagnosis: 'Migraña leve');

        $this->actingAs($user)->get(route('consultations.index', ['search' => 'Gripe']))->assertOk()->assertSee($match->diagnosis)->assertDontSee($other->diagnosis);
    }

    public function test_filter_by_doctor_id_works(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $doctor = $this->doctorForClinic($clinic, 'Doctor Filtro');
        $match = $this->consultationForClinic($clinic, doctor: $doctor, diagnosis: 'Doctor filtrado');
        $other = $this->consultationForClinic($clinic, doctor: $this->doctorForClinic($clinic, 'Otro Filtro'), diagnosis: 'Doctor no filtrado');

        $this->actingAs($user)->get(route('consultations.index', ['doctor_id' => $doctor->id]))->assertOk()->assertSee($match->diagnosis)->assertDontSee($other->diagnosis);
    }

    public function test_filter_by_date_works(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $match = $this->consultationForClinic($clinic, date: '2026-08-10 10:00:00', diagnosis: 'Fecha filtrada');
        $other = $this->consultationForClinic($clinic, date: '2026-08-11 10:00:00', diagnosis: 'Fecha no filtrada');

        $this->actingAs($user)->get(route('consultations.index', ['date' => '2026-08-10']))->assertOk()->assertSee($match->diagnosis)->assertDontSee($other->diagnosis);
    }

    public function test_user_cannot_view_consultation_from_other_clinic(): void
    {
        [$user, $consultation] = $this->userAndOtherClinicConsultation();
        $this->actingAs($user)->get(route('consultations.show', $consultation))->assertNotFound();
    }

    public function test_user_cannot_edit_consultation_from_other_clinic(): void
    {
        [$user, $consultation] = $this->userAndOtherClinicConsultation();
        $this->actingAs($user)->get(route('consultations.edit', $consultation))->assertNotFound();
    }

    public function test_user_cannot_update_consultation_from_other_clinic(): void
    {
        [$user, $consultation] = $this->userAndOtherClinicConsultation();

        $this->actingAs($user)
            ->put(route('consultations.update', $consultation), $this->validPayload($consultation->patient, $consultation->doctor, null, ['diagnosis' => 'No permitido']))
            ->assertNotFound();
    }

    public function test_user_cannot_delete_consultation_from_other_clinic(): void
    {
        [$user, $consultation] = $this->userAndOtherClinicConsultation();

        $this->actingAs($user)->delete(route('consultations.destroy', $consultation))->assertNotFound();
        $this->assertDatabaseHas('consultations', ['id' => $consultation->id]);
    }

    public function test_update_allows_keeping_same_appointment_id(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        [$patient, $doctor] = $this->relatedRecords($clinic);
        $appointment = $this->appointmentForClinic($clinic, $patient, $doctor, 'completed');
        $consultation = $this->consultationForClinic($clinic, $patient, $doctor, $appointment);

        $this->actingAs($user)
            ->put(route('consultations.update', $consultation), $this->validPayload($patient, $doctor, $appointment, ['diagnosis' => 'Misma cita']))
            ->assertRedirect(route('consultations.show', $consultation));

        $this->assertDatabaseHas('consultations', ['id' => $consultation->id, 'appointment_id' => $appointment->id, 'diagnosis' => 'Misma cita']);
    }

    public function test_update_cannot_use_appointment_id_already_used_by_another_consultation(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        [$patient, $doctor] = $this->relatedRecords($clinic);
        $usedAppointment = $this->appointmentForClinic($clinic, $patient, $doctor, 'completed', '2026-07-01', '08:00');
        $availableAppointment = $this->appointmentForClinic($clinic, $patient, $doctor, 'completed', '2026-07-02', '08:00');
        $this->consultationForClinic($clinic, $patient, $doctor, $usedAppointment);
        $consultation = $this->consultationForClinic($clinic, $patient, $doctor, $availableAppointment);

        $this->actingAs($user)
            ->from(route('consultations.edit', $consultation))
            ->put(route('consultations.update', $consultation), $this->validPayload($patient, $doctor, $usedAppointment))
            ->assertRedirect(route('consultations.edit', $consultation))
            ->assertSessionHasErrors('appointment_id');
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

    private function appointmentForClinic(Clinic $clinic, ?Patient $patient = null, ?Doctor $doctor = null, string $status = 'scheduled', string $date = '2026-07-01', string $time = '08:00'): Appointment
    {
        $patient ??= $this->patientForClinic($clinic);
        $doctor ??= $this->doctorForClinic($clinic);
        $service = Service::factory()->for($clinic)->create();

        $appointment = Appointment::factory()->for($clinic)->for($patient)->for($doctor)->for($service)->create([
            'appointment_date' => $date,
            'start_time' => $time,
            'end_time' => null,
            'reason' => 'Cita para consulta',
            'status' => $status,
        ]);

        Payment::factory()->forAppointment($appointment)->create([
            'amount' => $service->price,
            'payment_status' => 'paid',
            'payment_date' => '2026-07-01 07:30:00',
        ]);

        return $appointment;
    }

    private function consultationForClinic(Clinic $clinic, ?Patient $patient = null, ?Doctor $doctor = null, ?Appointment $appointment = null, string $diagnosis = 'Diagnóstico de prueba', string $date = '2026-07-10 09:00:00'): Consultation
    {
        $patient ??= $this->patientForClinic($clinic);
        $doctor ??= $this->doctorForClinic($clinic);

        return Consultation::factory()->for($patient)->for($doctor)->create([
            'appointment_id' => $appointment?->id,
            'reason' => 'Motivo de consulta',
            'symptoms' => 'Dolor general',
            'diagnosis' => $diagnosis,
            'treatment' => 'Tratamiento base',
            'consultation_date' => $date,
        ]);
    }

    private function validPayload(Patient $patient, Doctor $doctor, ?Appointment $appointment, array $overrides = []): array
    {
        return array_merge([
            'appointment_id' => $appointment?->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'reason' => 'Dolor abdominal',
            'symptoms' => 'Náuseas y dolor',
            'diagnosis' => 'Gastroenteritis',
            'treatment' => 'Hidratación y reposo',
            'observations' => 'Control si persiste',
            'weight' => '70.50',
            'height' => '1.72',
            'temperature' => '37.2',
            'blood_pressure' => '120/80',
            'heart_rate' => 82,
            'consultation_date' => '2026-07-15 10:00:00',
        ], $overrides);
    }

    private function setupForValidation(): array
    {
        $clinic = Clinic::factory()->create();
        return [$this->userForClinic($clinic), ...$this->relatedRecords($clinic)];
    }

    private function twoConsultations(string $matchingPatient, string $otherPatient): array
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $match = $this->consultationForClinic($clinic, patient: $this->patientForClinic($clinic, $matchingPatient), diagnosis: 'Consulta visible');
        $other = $this->consultationForClinic($clinic, patient: $this->patientForClinic($clinic, $otherPatient), diagnosis: 'Consulta oculta');
        return [$user, $match, $other];
    }

    private function userAndOtherClinicConsultation(): array
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        return [$this->userForClinic($clinic), $this->consultationForClinic($otherClinic)];
    }
}
