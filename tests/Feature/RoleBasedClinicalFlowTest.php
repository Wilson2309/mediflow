<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RoleBasedClinicalFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_receptionist_sees_new_patient_button(): void
    {
        [$clinic, $user] = $this->userWithRole('recepcionista');
        Patient::factory()->for($clinic)->create();

        $this->actingAs($user)
            ->get(route('patients.index'))
            ->assertOk()
            ->assertSee('Nuevo paciente');
    }

    public function test_doctor_does_not_see_new_patient_button(): void
    {
        [$clinic, $user, $doctor] = $this->doctorUser();
        $patient = Patient::factory()->for($clinic)->create(['first_name' => 'Paciente', 'last_name' => 'Asignado']);
        $this->appointmentFor($clinic, $doctor, $patient);

        $this->actingAs($user)
            ->get(route('patients.index'))
            ->assertOk()
            ->assertDontSee('Nuevo paciente');
    }

    public function test_finance_does_not_see_new_patient_button(): void
    {
        [$clinic, $user] = $this->userWithRole('caja_finanzas');
        Patient::factory()->for($clinic)->create();

        $this->actingAs($user)
            ->get(route('patients.index'))
            ->assertOk()
            ->assertDontSee('Nuevo paciente');
    }

    public function test_receptionist_sees_new_appointment_button(): void
    {
        [$clinic, $user] = $this->userWithRole('recepcionista');
        $doctor = $this->doctorForClinic($clinic);
        $this->appointmentFor($clinic, $doctor);

        $this->actingAs($user)
            ->get(route('appointments.index'))
            ->assertOk()
            ->assertSee('Nueva cita');
    }

    public function test_doctor_does_not_see_new_appointment_button(): void
    {
        [$clinic, $user, $doctor] = $this->doctorUser();
        $this->appointmentFor($clinic, $doctor);

        $this->actingAs($user)
            ->get(route('appointments.index'))
            ->assertOk()
            ->assertSee('Mis citas médicas')
            ->assertDontSee('Nueva cita');
    }

    public function test_finance_does_not_see_new_appointment_button(): void
    {
        [$clinic, $user] = $this->userWithRole('caja_finanzas');
        $doctor = $this->doctorForClinic($clinic);
        $this->appointmentFor($clinic, $doctor);

        $this->actingAs($user)
            ->get(route('appointments.index'))
            ->assertOk()
            ->assertDontSee('Nueva cita');
    }

    public function test_doctor_only_sees_assigned_appointments(): void
    {
        [$clinic, $user, $doctor] = $this->doctorUser();
        $ownPatient = Patient::factory()->for($clinic)->create(['first_name' => 'Visible', 'last_name' => 'Doctor']);
        $otherPatient = Patient::factory()->for($clinic)->create(['first_name' => 'Oculto', 'last_name' => 'Doctor']);
        $this->appointmentFor($clinic, $doctor, $ownPatient, 'Control visible');
        $this->appointmentFor($clinic, $this->doctorForClinic($clinic, 'Doctor ajeno'), $otherPatient, 'Control oculto');

        $this->actingAs($user)
            ->get(route('appointments.index'))
            ->assertOk()
            ->assertSee('Visible Doctor')
            ->assertSee('Control visible')
            ->assertDontSee('Oculto Doctor')
            ->assertDontSee('Control oculto');
    }

    public function test_doctor_cannot_view_appointment_from_other_doctor(): void
    {
        [$clinic, $user] = $this->doctorUser();
        $otherAppointment = $this->appointmentFor($clinic, $this->doctorForClinic($clinic, 'Doctor ajeno'));

        $this->actingAs($user)
            ->get(route('appointments.show', $otherAppointment))
            ->assertForbidden();
    }

    public function test_doctor_only_sees_patients_related_to_his_appointments_or_consultations(): void
    {
        [$clinic, $user, $doctor] = $this->doctorUser();
        $related = Patient::factory()->for($clinic)->create(['first_name' => 'Paciente', 'last_name' => 'Relacionado']);
        $unrelated = Patient::factory()->for($clinic)->create(['first_name' => 'Paciente', 'last_name' => 'Sinrelacion']);
        $this->appointmentFor($clinic, $doctor, $related);

        $this->actingAs($user)
            ->get(route('patients.index'))
            ->assertOk()
            ->assertSee('Paciente Relacionado')
            ->assertDontSee('Paciente Sinrelacion');
    }

    public function test_doctor_cannot_view_unrelated_patient(): void
    {
        [$clinic, $user] = $this->doctorUser();
        $patient = Patient::factory()->for($clinic)->create();

        $this->actingAs($user)
            ->get(route('patients.show', $patient))
            ->assertForbidden();
    }

    public function test_receptionist_can_see_all_patients_from_clinic(): void
    {
        [$clinic, $user] = $this->userWithRole('recepcionista');
        Patient::factory()->for($clinic)->create(['first_name' => 'Paciente', 'last_name' => 'Uno']);
        Patient::factory()->for($clinic)->create(['first_name' => 'Paciente', 'last_name' => 'Dos']);

        $this->actingAs($user)
            ->get(route('patients.index'))
            ->assertOk()
            ->assertSee('Paciente Uno')
            ->assertSee('Paciente Dos');
    }

    public function test_receptionist_can_create_appointment_selecting_doctor(): void
    {
        [$clinic, $user] = $this->userWithRole('recepcionista');
        $patient = Patient::factory()->for($clinic)->create();
        $doctor = $this->doctorForClinic($clinic, 'Doctor agenda');

        $this->actingAs($user)
            ->post(route('appointments.store'), [
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'service_id' => null,
                'appointment_date' => '2026-08-20',
                'start_time' => '09:30',
                'end_time' => null,
                'reason' => 'Consulta agendada por recepción',
                'status' => 'scheduled',
                'notes' => null,
            ])
            ->assertRedirect(route('appointments.index'));

        $this->assertDatabaseHas('appointments', [
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'reason' => 'Consulta agendada por recepción',
        ]);
    }

    public function test_assigned_appointment_shows_start_consultation_button_for_doctor(): void
    {
        [$clinic, $user, $doctor] = $this->doctorUser();
        $appointment = $this->appointmentFor($clinic, $doctor);
        $this->payAppointment($appointment);

        $this->actingAs($user)
            ->get(route('appointments.show', $appointment))
            ->assertOk()
            ->assertSee('Iniciar consulta');
    }

    public function test_other_doctor_appointment_returns_403_for_doctor(): void
    {
        [$clinic, $user] = $this->doctorUser();
        $appointment = $this->appointmentFor($clinic, $this->doctorForClinic($clinic, 'Doctor ajeno'));

        $this->actingAs($user)
            ->get(route('appointments.show', $appointment))
            ->assertForbidden();
    }

    public function test_start_consultation_from_appointment_prefills_patient_doctor_reason_and_date(): void
    {
        [$clinic, $user, $doctor] = $this->doctorUser();
        $patient = Patient::factory()->for($clinic)->create(['first_name' => 'Paciente', 'last_name' => 'Prefill']);
        $appointment = $this->appointmentFor($clinic, $doctor, $patient, 'Dolor abdominal', '2026-08-21', '10:15');
        $this->payAppointment($appointment);

        $this->actingAs($user)
            ->get(route('consultations.create', ['appointment_id' => $appointment->id]))
            ->assertOk()
            ->assertSee('value="'.$appointment->id.'" selected', false)
            ->assertSee('value="'.$patient->id.'" selected', false)
            ->assertSee('value="'.$doctor->id.'" selected', false)
            ->assertSee('Dolor abdominal')
            ->assertSee('value="2026-08-21T10:15"', false);
    }

    private function userWithRole(string $role): array
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $user->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return [$clinic, $user];
    }

    private function doctorUser(): array
    {
        [$clinic, $user] = $this->userWithRole('medico');
        $doctor = Doctor::factory()
            ->for($clinic)
            ->for($user)
            ->for(Specialty::factory()->create())
            ->create(['status' => 'active']);

        return [$clinic, $user, $doctor];
    }

    private function doctorForClinic(Clinic $clinic, string $name = 'Doctor visible'): Doctor
    {
        $user = User::factory()->create(['clinic_id' => $clinic->id, 'name' => $name]);

        return Doctor::factory()
            ->for($clinic)
            ->for($user)
            ->for(Specialty::factory()->create())
            ->create(['status' => 'active']);
    }

    private function payAppointment(Appointment $appointment): Payment
    {
        return Payment::factory()->forAppointment($appointment)->create([
            'amount' => 50,
            'payment_status' => 'paid',
            'payment_date' => '2026-08-20 08:30:00',
        ]);
    }
    private function appointmentFor(Clinic $clinic, Doctor $doctor, ?Patient $patient = null, string $reason = 'Control médico', string $date = '2026-08-20', string $startTime = '09:00'): Appointment
    {
        $patient ??= Patient::factory()->for($clinic)->create();

        return Appointment::factory()
            ->for($clinic)
            ->for($patient)
            ->for($doctor)
            ->create([
                'service_id' => null,
                'appointment_date' => $date,
                'start_time' => $startTime,
                'end_time' => null,
                'reason' => $reason,
                'status' => 'scheduled',
            ]);
    }
}