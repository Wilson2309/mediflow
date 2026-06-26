<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_appointments_index(): void
    {
        $this->get(route('appointments.index'))->assertRedirect(route('login', absolute: false));
    }

    public function test_authenticated_user_can_see_appointments_from_own_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $ownAppointment = $this->appointmentForClinic($clinic, reason: 'Control visible');
        $otherAppointment = $this->appointmentForClinic($otherClinic, reason: 'Control oculto');

        $this->actingAs($user)
            ->get(route('appointments.index'))
            ->assertOk()
            ->assertSee($ownAppointment->reason)
            ->assertDontSee($otherAppointment->reason);
    }

    public function test_authenticated_user_can_open_create_appointment_form(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $this->patientForClinic($clinic);
        $this->doctorForClinic($clinic);
        $this->serviceForClinic($clinic);

        $this->actingAs($user)
            ->get(route('appointments.create'))
            ->assertOk()
            ->assertSee('Nueva cita');
    }

    public function test_authenticated_user_can_create_valid_appointment(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        [$patient, $doctor, $service] = $this->relatedRecords($clinic);

        $this->actingAs($user)
            ->post(route('appointments.store'), $this->validPayload($patient, $doctor, $service, ['reason' => 'Primera consulta']))
            ->assertRedirect(route('appointments.index'))
            ->assertSessionHas('success', 'Cita creada correctamente.');

        $this->assertDatabaseHas('appointments', [
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'service_id' => $service->id,
            'reason' => 'Primera consulta',
            'status' => 'scheduled',
        ]);
    }

    public function test_appointment_cannot_be_created_without_patient_id(): void
    {
        [$user, $patient, $doctor, $service] = $this->setupForValidation();

        $this->actingAs($user)
            ->from(route('appointments.create'))
            ->post(route('appointments.store'), $this->validPayload($patient, $doctor, $service, ['patient_id' => '']))
            ->assertRedirect(route('appointments.create'))
            ->assertSessionHasErrors('patient_id');
    }

    public function test_appointment_cannot_be_created_without_doctor_id(): void
    {
        [$user, $patient, $doctor, $service] = $this->setupForValidation();

        $this->actingAs($user)
            ->from(route('appointments.create'))
            ->post(route('appointments.store'), $this->validPayload($patient, $doctor, $service, ['doctor_id' => '']))
            ->assertRedirect(route('appointments.create'))
            ->assertSessionHasErrors('doctor_id');
    }

    public function test_appointment_cannot_be_created_without_appointment_date(): void
    {
        [$user, $patient, $doctor, $service] = $this->setupForValidation();

        $this->actingAs($user)
            ->from(route('appointments.create'))
            ->post(route('appointments.store'), $this->validPayload($patient, $doctor, $service, ['appointment_date' => '']))
            ->assertRedirect(route('appointments.create'))
            ->assertSessionHasErrors('appointment_date');
    }

    public function test_appointment_cannot_be_created_without_start_time(): void
    {
        [$user, $patient, $doctor, $service] = $this->setupForValidation();

        $this->actingAs($user)
            ->from(route('appointments.create'))
            ->post(route('appointments.store'), $this->validPayload($patient, $doctor, $service, ['start_time' => '']))
            ->assertRedirect(route('appointments.create'))
            ->assertSessionHasErrors('start_time');
    }

    public function test_appointment_cannot_be_created_with_invalid_status(): void
    {
        [$user, $patient, $doctor, $service] = $this->setupForValidation();

        $this->actingAs($user)
            ->from(route('appointments.create'))
            ->post(route('appointments.store'), $this->validPayload($patient, $doctor, $service, ['status' => 'pendiente']))
            ->assertRedirect(route('appointments.create'))
            ->assertSessionHasErrors('status');
    }

    public function test_appointment_cannot_be_created_with_patient_from_other_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        [$patient, $doctor, $service] = $this->relatedRecords($clinic);
        $otherPatient = $this->patientForClinic($otherClinic);

        $this->actingAs($user)
            ->from(route('appointments.create'))
            ->post(route('appointments.store'), $this->validPayload($patient, $doctor, $service, ['patient_id' => $otherPatient->id]))
            ->assertRedirect(route('appointments.create'))
            ->assertSessionHasErrors('clinic_id');
    }

    public function test_appointment_cannot_be_created_with_doctor_from_other_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        [$patient, $doctor, $service] = $this->relatedRecords($clinic);
        $otherDoctor = $this->doctorForClinic($otherClinic);

        $this->actingAs($user)
            ->from(route('appointments.create'))
            ->post(route('appointments.store'), $this->validPayload($patient, $doctor, $service, ['doctor_id' => $otherDoctor->id]))
            ->assertRedirect(route('appointments.create'))
            ->assertSessionHasErrors('clinic_id');
    }

    public function test_appointment_cannot_be_created_with_service_from_other_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        [$patient, $doctor, $service] = $this->relatedRecords($clinic);
        $otherService = $this->serviceForClinic($otherClinic);

        $this->actingAs($user)
            ->from(route('appointments.create'))
            ->post(route('appointments.store'), $this->validPayload($patient, $doctor, $service, ['service_id' => $otherService->id]))
            ->assertRedirect(route('appointments.create'))
            ->assertSessionHasErrors('clinic_id');
    }

    public function test_active_appointment_cannot_be_duplicated_for_same_doctor_date_and_start_time(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        [$patient, $doctor, $service] = $this->relatedRecords($clinic);
        $this->appointmentForClinic($clinic, $patient, $doctor, $service, 'scheduled', '2026-07-01', '09:00');

        $this->actingAs($user)
            ->from(route('appointments.create'))
            ->post(route('appointments.store'), $this->validPayload($patient, $doctor, $service, ['appointment_date' => '2026-07-01', 'start_time' => '09:00']))
            ->assertRedirect(route('appointments.create'))
            ->assertSessionHasErrors('start_time');
    }

    public function test_same_time_is_allowed_when_previous_appointment_is_cancelled(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        [$patient, $doctor, $service] = $this->relatedRecords($clinic);
        $this->appointmentForClinic($clinic, $patient, $doctor, $service, 'cancelled', '2026-07-01', '09:00');

        $this->actingAs($user)
            ->post(route('appointments.store'), $this->validPayload($patient, $doctor, $service, ['appointment_date' => '2026-07-01', 'start_time' => '09:00']))
            ->assertRedirect(route('appointments.index'));
    }

    public function test_authenticated_user_can_view_appointment_from_own_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $appointment = $this->appointmentForClinic($clinic, reason: 'Vista cita');

        $this->actingAs($user)->get(route('appointments.show', $appointment))->assertOk()->assertSee('Vista cita');
    }

    public function test_authenticated_user_can_edit_appointment_from_own_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $appointment = $this->appointmentForClinic($clinic);

        $this->actingAs($user)->get(route('appointments.edit', $appointment))->assertOk()->assertSee('Editar cita');
    }

    public function test_authenticated_user_can_update_appointment_from_own_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        [$patient, $doctor, $service] = $this->relatedRecords($clinic);
        $appointment = $this->appointmentForClinic($clinic, $patient, $doctor, $service);

        $this->actingAs($user)
            ->put(route('appointments.update', $appointment), $this->validPayload($patient, $doctor, $service, ['reason' => 'Motivo actualizado', 'status' => 'confirmed']))
            ->assertRedirect(route('appointments.show', $appointment))
            ->assertSessionHas('success', 'Cita actualizada correctamente.');

        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'reason' => 'Motivo actualizado', 'status' => 'confirmed']);
    }

    public function test_authenticated_user_can_delete_appointment_from_own_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $appointment = $this->appointmentForClinic($clinic);

        $this->actingAs($user)
            ->delete(route('appointments.destroy', $appointment))
            ->assertRedirect(route('appointments.index'))
            ->assertSessionHas('success', 'Cita eliminada correctamente.');

        $this->assertDatabaseMissing('appointments', ['id' => $appointment->id]);
    }

    public function test_search_by_patient_works(): void
    {
        [$user, $match, $other] = $this->twoAppointments('Paciente Buscable', 'Paciente Oculto');

        $this->actingAs($user)->get(route('appointments.index', ['search' => 'Buscable']))->assertOk()->assertSee($match->reason)->assertDontSee($other->reason);
    }

    public function test_search_by_doctor_works(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $match = $this->appointmentForClinic($clinic, doctor: $this->doctorForClinic($clinic, 'Doctor Agenda'), reason: 'Doctor match');
        $other = $this->appointmentForClinic($clinic, doctor: $this->doctorForClinic($clinic, 'Doctor Otro'), reason: 'Doctor other');

        $this->actingAs($user)->get(route('appointments.index', ['search' => 'Agenda']))->assertOk()->assertSee($match->reason)->assertDontSee($other->reason);
    }

    public function test_filter_by_scheduled_status_works(): void
    {
        [$user, $match, $other] = $this->twoAppointmentsByStatus('scheduled', 'confirmed');
        $this->actingAs($user)->get(route('appointments.index', ['status' => 'scheduled']))->assertOk()->assertSee($match->reason)->assertDontSee($other->reason);
    }

    public function test_filter_by_confirmed_status_works(): void
    {
        [$user, $match, $other] = $this->twoAppointmentsByStatus('confirmed', 'scheduled');
        $this->actingAs($user)->get(route('appointments.index', ['status' => 'confirmed']))->assertOk()->assertSee($match->reason)->assertDontSee($other->reason);
    }

    public function test_filter_by_doctor_id_works(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $doctor = $this->doctorForClinic($clinic, 'Doctor Filtro');
        $match = $this->appointmentForClinic($clinic, doctor: $doctor, reason: 'Doctor filtrado');
        $other = $this->appointmentForClinic($clinic, doctor: $this->doctorForClinic($clinic, 'Otro Filtro'), reason: 'Doctor no filtrado');

        $this->actingAs($user)->get(route('appointments.index', ['doctor_id' => $doctor->id]))->assertOk()->assertSee($match->reason)->assertDontSee($other->reason);
    }

    public function test_filter_by_date_works(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $match = $this->appointmentForClinic($clinic, date: '2026-08-10', reason: 'Fecha filtrada');
        $other = $this->appointmentForClinic($clinic, date: '2026-08-11', reason: 'Fecha no filtrada');

        $this->actingAs($user)->get(route('appointments.index', ['date' => '2026-08-10']))->assertOk()->assertSee($match->reason)->assertDontSee($other->reason);
    }

    public function test_user_cannot_view_appointment_from_other_clinic(): void
    {
        [$user, $appointment] = $this->userAndOtherClinicAppointment();
        $this->actingAs($user)->get(route('appointments.show', $appointment))->assertForbidden();
    }

    public function test_user_cannot_edit_appointment_from_other_clinic(): void
    {
        [$user, $appointment] = $this->userAndOtherClinicAppointment();
        $this->actingAs($user)->get(route('appointments.edit', $appointment))->assertForbidden();
    }

    public function test_user_cannot_update_appointment_from_other_clinic(): void
    {
        [$user, $appointment] = $this->userAndOtherClinicAppointment();
        $this->actingAs($user)->put(route('appointments.update', $appointment), [
            'patient_id' => $appointment->patient_id,
            'doctor_id' => $appointment->doctor_id,
            'service_id' => $appointment->service_id,
            'appointment_date' => '2026-09-01',
            'start_time' => '10:00',
            'end_time' => null,
            'reason' => 'No permitido',
            'status' => 'scheduled',
            'notes' => null,
        ])->assertForbidden();
    }

    public function test_user_cannot_delete_appointment_from_other_clinic(): void
    {
        [$user, $appointment] = $this->userAndOtherClinicAppointment();
        $this->actingAs($user)->delete(route('appointments.destroy', $appointment))->assertForbidden();
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id]);
    }

    public function test_end_time_is_calculated_from_service_duration_when_empty(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        [$patient, $doctor, $service] = $this->relatedRecords($clinic);
        $service->update(['duration_minutes' => 45]);

        $this->actingAs($user)
            ->post(route('appointments.store'), $this->validPayload($patient, $doctor, $service, ['start_time' => '09:15', 'end_time' => null]))
            ->assertRedirect(route('appointments.index'));

        $this->assertDatabaseHas('appointments', ['start_time' => '09:15', 'end_time' => '10:00']);
    }

    public function test_patient_search_endpoint_respects_clinic_scope(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $patient = $this->patientForClinic($clinic, 'Paciente Busqueda');
        $otherPatient = $this->patientForClinic($otherClinic, 'Paciente Oculto');

        $this->actingAs($user)
            ->getJson(route('appointments.patients.search', ['q' => 'Paciente']))
            ->assertOk()
            ->assertJsonFragment(['id' => $patient->id, 'label' => $patient->full_name])
            ->assertJsonMissing(['id' => $otherPatient->id, 'label' => $otherPatient->full_name]);
    }

    public function test_doctor_search_endpoint_respects_clinic_scope(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $doctor = $this->doctorForClinic($clinic, 'Doctor Buscable');
        $otherDoctor = $this->doctorForClinic($otherClinic, 'Doctor Oculto');

        $this->actingAs($user)
            ->getJson(route('appointments.doctors.search', ['q' => 'Doctor']))
            ->assertOk()
            ->assertJsonFragment(['id' => $doctor->id, 'label' => 'Doctor Buscable'])
            ->assertJsonMissing(['id' => $otherDoctor->id, 'label' => 'Doctor Oculto']);
    }

    public function test_service_filter_returns_only_compatible_doctors(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $service = $this->serviceForClinic($clinic);
        $compatibleDoctor = $this->doctorForClinic($clinic, 'Doctor Compatible');
        $incompatibleDoctor = $this->doctorForClinic($clinic, 'Doctor Incompatible');
        $compatibleDoctor->services()->attach($service);

        $this->actingAs($user)
            ->getJson(route('appointments.doctors.search', ['service_id' => $service->id]))
            ->assertOk()
            ->assertJsonFragment(['id' => $compatibleDoctor->id, 'label' => 'Doctor Compatible'])
            ->assertJsonMissing(['id' => $incompatibleDoctor->id, 'label' => 'Doctor Incompatible']);
    }

    public function test_appointment_cannot_be_created_with_doctor_that_does_not_offer_service(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $patient = $this->patientForClinic($clinic);
        $doctor = $this->doctorForClinic($clinic);
        $service = $this->serviceForClinic($clinic);

        $this->actingAs($user)
            ->from(route('appointments.create'))
            ->post(route('appointments.store'), $this->validPayload($patient, $doctor, $service))
            ->assertRedirect(route('appointments.create'))
            ->assertSessionHasErrors('doctor_id');
    }

    public function test_overlapping_appointment_is_rejected_using_service_duration(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        [$patient, $doctor, $service] = $this->relatedRecords($clinic);
        $service->update(['duration_minutes' => 30]);
        $this->appointmentForClinic($clinic, $patient, $doctor, $service, 'scheduled', '2026-07-01', '09:00');

        $this->actingAs($user)
            ->from(route('appointments.create'))
            ->post(route('appointments.store'), $this->validPayload($patient, $doctor, $service, ['appointment_date' => '2026-07-01', 'start_time' => '09:15']))
            ->assertRedirect(route('appointments.create'))
            ->assertSessionHasErrors('start_time');
    }

    public function test_no_show_appointment_does_not_block_same_time(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        [$patient, $doctor, $service] = $this->relatedRecords($clinic);
        $this->appointmentForClinic($clinic, $patient, $doctor, $service, 'no_show', '2026-07-01', '09:00');

        $this->actingAs($user)
            ->post(route('appointments.store'), $this->validPayload($patient, $doctor, $service, ['appointment_date' => '2026-07-01', 'start_time' => '09:00']))
            ->assertRedirect(route('appointments.index'));
    }

    public function test_editing_appointment_does_not_conflict_with_itself(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        [$patient, $doctor, $service] = $this->relatedRecords($clinic);
        $appointment = $this->appointmentForClinic($clinic, $patient, $doctor, $service, 'scheduled', '2026-07-01', '09:00');

        $this->actingAs($user)
            ->put(route('appointments.update', $appointment), $this->validPayload($patient, $doctor, $service, ['appointment_date' => '2026-07-01', 'start_time' => '09:00']))
            ->assertRedirect(route('appointments.show', $appointment));
    }

    public function test_changing_service_revalidates_doctor_compatibility(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        [$patient, $doctor, $service] = $this->relatedRecords($clinic);
        $newService = $this->serviceForClinic($clinic);
        $appointment = $this->appointmentForClinic($clinic, $patient, $doctor, $service);

        $this->actingAs($user)
            ->from(route('appointments.edit', $appointment))
            ->put(route('appointments.update', $appointment), $this->validPayload($patient, $doctor, $newService))
            ->assertRedirect(route('appointments.edit', $appointment))
            ->assertSessionHasErrors('doctor_id');
    }

    public function test_changing_date_or_time_revalidates_availability(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        [$patient, $doctor, $service] = $this->relatedRecords($clinic);
        $appointment = $this->appointmentForClinic($clinic, $patient, $doctor, $service, 'scheduled', '2026-07-01', '09:00');
        $otherAppointment = $this->appointmentForClinic($clinic, $this->patientForClinic($clinic, 'Otro Paciente'), $doctor, $service, 'scheduled', '2026-07-02', '10:00');

        $this->actingAs($user)
            ->from(route('appointments.edit', $appointment))
            ->put(route('appointments.update', $appointment), $this->validPayload($patient, $doctor, $service, ['appointment_date' => '2026-07-02', 'start_time' => '10:00']))
            ->assertRedirect(route('appointments.edit', $appointment))
            ->assertSessionHasErrors('start_time');

        $this->assertDatabaseHas('appointments', ['id' => $otherAppointment->id, 'start_time' => '10:00']);
    }

    public function test_availability_endpoint_returns_available_and_unavailable_slots(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        [$patient, $doctor, $service] = $this->relatedRecords($clinic);
        $this->appointmentForClinic($clinic, $patient, $doctor, $service, 'scheduled', '2026-07-01', '09:00');

        $this->actingAs($user)
            ->getJson(route('appointments.availability', [
                'doctor_id' => $doctor->id,
                'service_id' => $service->id,
                'date' => '2026-07-01',
            ]))
            ->assertOk()
            ->assertJsonFragment(['duration' => 30])
            ->assertJsonPath('unavailable_slots.1', '09:00');
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

    private function serviceForClinic(Clinic $clinic): Service
    {
        return Service::factory()->for($clinic)->create(['duration_minutes' => 30]);
    }

    private function relatedRecords(Clinic $clinic): array
    {
        $patient = $this->patientForClinic($clinic);
        $doctor = $this->doctorForClinic($clinic);
        $service = $this->serviceForClinic($clinic);
        $doctor->services()->syncWithoutDetaching([$service->id]);

        return [$patient, $doctor, $service];
    }

    private function appointmentForClinic(Clinic $clinic, ?Patient $patient = null, ?Doctor $doctor = null, ?Service $service = null, string $status = 'scheduled', string $date = '2026-07-01', string $time = '08:00', string $reason = 'Cita de prueba'): Appointment
    {
        $patient ??= $this->patientForClinic($clinic);
        $doctor ??= $this->doctorForClinic($clinic);
        $service ??= $this->serviceForClinic($clinic);
        $doctor->services()->syncWithoutDetaching([$service->id]);

        return Appointment::factory()->for($clinic)->for($patient)->for($doctor)->for($service)->create([
            'appointment_date' => $date,
            'start_time' => $time,
            'end_time' => null,
            'reason' => $reason,
            'status' => $status,
        ]);
    }

    private function validPayload(Patient $patient, Doctor $doctor, ?Service $service, array $overrides = []): array
    {
        return array_merge([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'service_id' => $service?->id,
            'appointment_date' => '2026-07-15',
            'start_time' => '10:00',
            'end_time' => null,
            'reason' => 'Consulta general',
            'status' => 'scheduled',
            'notes' => 'Llegar 10 minutos antes',
        ], $overrides);
    }

    private function setupForValidation(): array
    {
        $clinic = Clinic::factory()->create();
        return [$this->userForClinic($clinic), ...$this->relatedRecords($clinic)];
    }

    private function twoAppointments(string $matchingPatient, string $otherPatient): array
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $match = $this->appointmentForClinic($clinic, patient: $this->patientForClinic($clinic, $matchingPatient), reason: 'Cita visible');
        $other = $this->appointmentForClinic($clinic, patient: $this->patientForClinic($clinic, $otherPatient), reason: 'Cita oculta');
        return [$user, $match, $other];
    }

    private function twoAppointmentsByStatus(string $matchingStatus, string $otherStatus): array
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $match = $this->appointmentForClinic($clinic, status: $matchingStatus, reason: 'Estado visible');
        $other = $this->appointmentForClinic($clinic, status: $otherStatus, reason: 'Estado oculto');
        return [$user, $match, $other];
    }

    private function userAndOtherClinicAppointment(): array
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        return [$this->userForClinic($clinic), $this->appointmentForClinic($otherClinic)];
    }
}


