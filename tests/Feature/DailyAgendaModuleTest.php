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
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DailyAgendaModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->travelTo('2026-08-01 08:00:00');
    }

    public function test_guest_cannot_access_daily_agenda(): void
    {
        $this->get(route('daily-agenda.index'))->assertRedirect(route('login', absolute: false));
    }

    public function test_user_with_appointments_view_can_access_daily_agenda(): void
    {
        [, $user] = $this->userWithRole('recepcionista');

        $this->actingAs($user)
            ->get(route('daily-agenda.index'))
            ->assertOk()
            ->assertSee('Agenda del dia');
    }

    public function test_receptionist_sees_all_clinic_appointments(): void
    {
        [$clinic, $user] = $this->userWithRole('recepcionista');
        $otherClinic = Clinic::factory()->create();
        $visible = $this->appointmentForClinic($clinic, patientName: 'Paciente Visible');
        $otherDoctor = $this->doctorForClinic($clinic, 'Doctor Dos');
        $secondVisible = $this->appointmentForClinic($clinic, $otherDoctor, patientName: 'Paciente Segunda', time: '10:00');
        $hidden = $this->appointmentForClinic($otherClinic, patientName: 'Paciente Oculta');

        $this->actingAs($user)
            ->get(route('daily-agenda.index'))
            ->assertOk()
            ->assertSee($visible->patient->full_name)
            ->assertSee($secondVisible->patient->full_name)
            ->assertDontSee($hidden->patient->full_name);
    }

    public function test_doctor_sees_only_assigned_appointments(): void
    {
        [$clinic, $doctorUser, $doctor] = $this->doctorUser();
        $own = $this->appointmentForClinic($clinic, $doctor, patientName: 'Paciente Propio');
        $other = $this->appointmentForClinic($clinic, $this->doctorForClinic($clinic, 'Doctor Ajeno'), patientName: 'Paciente Ajeno', time: '11:00');

        $this->actingAs($doctorUser)
            ->get(route('daily-agenda.index'))
            ->assertOk()
            ->assertSee($own->patient->full_name)
            ->assertDontSee($other->patient->full_name);
    }

    public function test_doctor_does_not_see_other_doctor_appointments(): void
    {
        [$clinic, $doctorUser, $doctor] = $this->doctorUser();
        $this->appointmentForClinic($clinic, $doctor, patientName: 'Paciente del Medico');
        $other = $this->appointmentForClinic($clinic, $this->doctorForClinic($clinic, 'Medico Externo'), patientName: 'Paciente de Otro', time: '12:00');

        $this->actingAs($doctorUser)
            ->get(route('daily-agenda.index', ['search' => 'Otro']))
            ->assertOk()
            ->assertDontSee($other->patient->full_name);
    }

    public function test_cashier_sees_collection_data(): void
    {
        [$clinic, $cashier] = $this->userWithRole('caja_finanzas');
        $appointment = $this->appointmentForClinic($clinic, patientName: 'Paciente Caja');
        Payment::factory()->forAppointment($appointment)->create(['amount' => 45, 'payment_status' => 'pending']);

        $this->actingAs($cashier)
            ->get(route('daily-agenda.index'))
            ->assertOk()
            ->assertSee('Paciente Caja')
            ->assertSee('Pendiente de pago')
            ->assertSee('$45.00')
            ->assertSee('Cobrar')
            ->assertDontSee('Iniciar consulta');
    }

    public function test_agenda_shows_payment_pending(): void
    {
        [$clinic, $user] = $this->userWithRole('recepcionista');
        $appointment = $this->appointmentForClinic($clinic, patientName: 'Paciente Pendiente');
        Payment::factory()->forAppointment($appointment)->create(['payment_status' => 'pending']);

        $this->actingAs($user)
            ->get(route('daily-agenda.index'))
            ->assertOk()
            ->assertSee('Paciente Pendiente')
            ->assertSee('Pendiente de pago');
    }

    public function test_agenda_shows_payment_paid(): void
    {
        [$clinic, $user] = $this->userWithRole('recepcionista');
        $appointment = $this->appointmentForClinic($clinic, patientName: 'Paciente Pagado');
        Payment::factory()->forAppointment($appointment)->create(['payment_status' => 'paid', 'payment_date' => '2026-08-01 07:30:00']);

        $this->actingAs($user)
            ->get(route('daily-agenda.index'))
            ->assertOk()
            ->assertSee('Paciente Pagado')
            ->assertSee('Pagado');
    }

    public function test_agenda_shows_completed_appointment(): void
    {
        [$clinic, $user] = $this->userWithRole('recepcionista');
        $this->appointmentForClinic($clinic, patientName: 'Paciente Atendido', status: 'completed');

        $this->actingAs($user)
            ->get(route('daily-agenda.index'))
            ->assertOk()
            ->assertSee('Paciente Atendido')
            ->assertSee('Atendida');
    }

    public function test_filter_by_date_works(): void
    {
        [$clinic, $user] = $this->userWithRole('recepcionista');
        $match = $this->appointmentForClinic($clinic, patientName: 'Paciente Fecha', date: '2026-08-02');
        $other = $this->appointmentForClinic($clinic, patientName: 'Paciente Otra Fecha', date: '2026-08-01');

        $this->actingAs($user)
            ->get(route('daily-agenda.index', ['date' => '2026-08-02']))
            ->assertOk()
            ->assertSee($match->patient->full_name)
            ->assertDontSee($other->patient->full_name);
    }

    public function test_filter_by_doctor_works_for_reception(): void
    {
        [$clinic, $user] = $this->userWithRole('recepcionista');
        $doctor = $this->doctorForClinic($clinic, 'Doctor Filtrado');
        $match = $this->appointmentForClinic($clinic, $doctor, patientName: 'Paciente Filtrado');
        $other = $this->appointmentForClinic($clinic, $this->doctorForClinic($clinic, 'Doctor No Filtrado'), patientName: 'Paciente No Filtrado', time: '11:30');

        $this->actingAs($user)
            ->get(route('daily-agenda.index', ['doctor_id' => $doctor->id]))
            ->assertOk()
            ->assertSee($match->patient->full_name)
            ->assertDontSee($other->patient->full_name);
    }

    public function test_search_by_patient_works(): void
    {
        [$clinic, $user] = $this->userWithRole('recepcionista');
        $match = $this->appointmentForClinic($clinic, patientName: 'Paciente Buscable');
        $other = $this->appointmentForClinic($clinic, patientName: 'Paciente Oculto', time: '13:00');

        $this->actingAs($user)
            ->get(route('daily-agenda.index', ['search' => 'Buscable']))
            ->assertOk()
            ->assertSee($match->patient->full_name)
            ->assertDontSee($other->patient->full_name);
    }

    public function test_doctor_sees_start_consultation_only_when_payment_is_paid(): void
    {
        [$clinic, $doctorUser, $doctor] = $this->doctorUser();
        $pending = $this->appointmentForClinic($clinic, $doctor, patientName: 'Paciente Sin Pago');
        Payment::factory()->forAppointment($pending)->create(['payment_status' => 'pending']);
        $paid = $this->appointmentForClinic($clinic, $doctor, patientName: 'Paciente Listo', time: '10:30');
        Payment::factory()->forAppointment($paid)->create(['payment_status' => 'paid', 'payment_date' => '2026-08-01 08:30:00']);

        $this->actingAs($doctorUser)
            ->get(route('daily-agenda.index', ['search' => 'Sin Pago']))
            ->assertOk()
            ->assertSee('Paciente Sin Pago')
            ->assertSee('Pendiente de pago')
            ->assertDontSee('Iniciar consulta');

        $this->actingAs($doctorUser)
            ->get(route('daily-agenda.index', ['search' => 'Listo']))
            ->assertOk()
            ->assertSee('Paciente Listo')
            ->assertSee('Iniciar consulta');
    }

    public function test_doctor_agenda_shows_one_payment_badge_and_distinct_warning(): void
    {
        [$clinic, $doctorUser, $doctor] = $this->doctorUser();
        $appointment = $this->appointmentForClinic($clinic, $doctor, patientName: 'Paciente Pago Pendiente');
        Payment::factory()->forAppointment($appointment)->create(['payment_status' => 'pending']);

        $response = $this->actingAs($doctorUser)
            ->get(route('daily-agenda.index', ['search' => 'Pago Pendiente']))
            ->assertOk()
            ->assertSee('Paciente Pago Pendiente')
            ->assertSee('Pendiente de pago')
            ->assertSee('No puede iniciar consulta hasta que caja registre el pago.')
            ->assertDontSee('<p class="mt-2 text-xs font-semibold text-[#B45309]">Pendiente de pago</p>', false);

        $this->assertSame(1, substr_count($response->getContent(), 'No puede iniciar consulta hasta que caja registre el pago.'));
    }

    public function test_doctor_does_not_see_collect_button(): void
    {
        [$clinic, $doctorUser, $doctor] = $this->doctorUser();
        $appointment = $this->appointmentForClinic($clinic, $doctor, patientName: 'Paciente Cobro');
        Payment::factory()->forAppointment($appointment)->create(['payment_status' => 'pending']);

        $this->actingAs($doctorUser)
            ->get(route('daily-agenda.index'))
            ->assertOk()
            ->assertSee('Paciente Cobro')
            ->assertDontSee('Cobrar');
    }

    public function test_cashier_sees_collect_button_for_pending_payment(): void
    {
        [$clinic, $cashier] = $this->userWithRole('caja_finanzas');
        $appointment = $this->appointmentForClinic($clinic, patientName: 'Paciente Cobrar');
        Payment::factory()->forAppointment($appointment)->create(['payment_status' => 'pending']);

        $this->actingAs($cashier)
            ->get(route('daily-agenda.index'))
            ->assertOk()
            ->assertSee('Paciente Cobrar')
            ->assertSee('Cobrar');
    }

    public function test_reception_sees_new_appointment_button(): void
    {
        [, $user] = $this->userWithRole('recepcionista');

        $this->actingAs($user)
            ->get(route('daily-agenda.index'))
            ->assertOk()
            ->assertSee('Nueva cita');
    }

    public function test_creating_consultation_from_appointment_marks_appointment_completed(): void
    {
        [$clinic, $doctorUser, $doctor] = $this->doctorUser();
        $appointment = $this->appointmentForClinic($clinic, $doctor, patientName: 'Paciente Consulta', status: 'confirmed');
        Payment::factory()->forAppointment($appointment)->create(['payment_status' => 'paid', 'payment_date' => '2026-08-01 08:20:00']);

        $this->actingAs($doctorUser)
            ->post(route('consultations.store'), $this->consultationPayload($appointment))
            ->assertRedirect(route('consultations.index'));

        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'completed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'appointment.completed', 'module' => 'appointments']);
    }

    public function test_cancel_from_daily_agenda_cancels_pending_payment_and_is_audited(): void
    {
        [$clinic, $user] = $this->userWithRole('recepcionista');
        $appointment = $this->appointmentForClinic($clinic, patientName: 'Paciente Cancelado');
        $payment = Payment::factory()->forAppointment($appointment)->create(['payment_status' => 'pending']);

        $this->actingAs($user)
            ->patch(route('daily-agenda.appointments.cancel', $appointment))
            ->assertRedirect(route('daily-agenda.index', ['date' => '2026-08-01']));

        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'payment_status' => 'cancelled']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'appointment.cancelled', 'module' => 'appointments']);
    }

    public function test_mark_no_show_from_daily_agenda_is_audited(): void
    {
        [$clinic, $user] = $this->userWithRole('recepcionista');
        $appointment = $this->appointmentForClinic($clinic, patientName: 'Paciente Ausente');

        $this->actingAs($user)
            ->patch(route('daily-agenda.appointments.no-show', $appointment))
            ->assertRedirect(route('daily-agenda.index', ['date' => '2026-08-01']));

        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'no_show']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'appointment.marked_no_show', 'module' => 'appointments']);
    }

    private function userWithRole(string $role, ?Clinic $clinic = null): array
    {
        $clinic ??= Clinic::factory()->create();
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
            ->create(['status' => 'active', 'consultation_fee' => 50]);

        return [$clinic, $user, $doctor];
    }

    private function doctorForClinic(Clinic $clinic, string $name = 'Doctor Test'): Doctor
    {
        $user = User::factory()->create(['clinic_id' => $clinic->id, 'name' => $name]);

        return Doctor::factory()
            ->for($clinic)
            ->for($user)
            ->for(Specialty::factory()->create())
            ->create(['status' => 'active', 'consultation_fee' => 50]);
    }

    private function appointmentForClinic(
        Clinic $clinic,
        ?Doctor $doctor = null,
        string $patientName = 'Paciente Test',
        string $date = '2026-08-01',
        string $time = '09:00',
        string $status = 'scheduled',
    ): Appointment {
        $doctor ??= $this->doctorForClinic($clinic);
        $patient = $this->patientForClinic($clinic, $patientName);
        $service = Service::factory()->for($clinic)->create(['name' => 'Consulta general', 'price' => 30, 'status' => 'active']);

        return Appointment::factory()
            ->for($clinic)
            ->for($patient)
            ->for($doctor)
            ->for($service)
            ->create([
                'appointment_date' => $date,
                'start_time' => $time,
                'end_time' => null,
                'reason' => 'Consulta de agenda',
                'status' => $status,
            ]);
    }

    private function patientForClinic(Clinic $clinic, string $name): Patient
    {
        [$first, $last] = array_pad(explode(' ', $name, 2), 2, 'Test');

        return Patient::factory()->for($clinic)->create([
            'first_name' => $first,
            'last_name' => $last,
        ]);
    }

    private function consultationPayload(Appointment $appointment): array
    {
        return [
            'appointment_id' => $appointment->id,
            'patient_id' => $appointment->patient_id,
            'doctor_id' => $appointment->doctor_id,
            'reason' => 'Dolor abdominal',
            'symptoms' => 'Dolor y nauseas',
            'diagnosis' => 'Gastroenteritis',
            'treatment' => 'Hidratacion y reposo',
            'observations' => 'Control en 48 horas',
            'weight' => '70.50',
            'height' => '1.72',
            'temperature' => '37.2',
            'blood_pressure' => '120/80',
            'heart_rate' => 82,
            'consultation_date' => '2026-08-01 09:20:00',
        ];
    }
}
