<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ReceptionCashDoctorFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_reception_creating_appointment_generates_pending_payment_from_service_price(): void
    {
        [$clinic, $user] = $this->userWithRole('recepcionista');
        $patient = $this->patientForClinic($clinic);
        $doctor = $this->doctorForClinic($clinic, fee: 60);
        $service = $this->serviceForClinic($clinic, price: 35);

        $this->actingAs($user)
            ->post(route('appointments.store'), $this->appointmentPayload($patient, $doctor, $service))
            ->assertRedirect(route('appointments.index'));

        $appointment = Appointment::where('patient_id', $patient->id)->firstOrFail();

        $this->assertDatabaseHas('payments', [
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'amount' => 35,
            'payment_status' => 'pending',
            'payment_date' => null,
        ]);
    }

    public function test_pending_payment_uses_doctor_fee_when_appointment_has_no_service(): void
    {
        [$clinic, $user] = $this->userWithRole('recepcionista');
        $patient = $this->patientForClinic($clinic);
        $doctor = $this->doctorForClinic($clinic, fee: 65);

        $this->actingAs($user)
            ->post(route('appointments.store'), $this->appointmentPayload($patient, $doctor, null))
            ->assertRedirect(route('appointments.index'));

        $appointment = Appointment::where('patient_id', $patient->id)->firstOrFail();

        $this->assertDatabaseHas('payments', [
            'appointment_id' => $appointment->id,
            'amount' => 65,
            'payment_status' => 'pending',
        ]);
    }

    public function test_pending_payment_can_be_created_with_amount_zero_when_no_price_is_available(): void
    {
        [$clinic, $user] = $this->userWithRole('recepcionista');
        $patient = $this->patientForClinic($clinic);
        $doctor = $this->doctorForClinic($clinic, fee: 0);

        $this->actingAs($user)
            ->post(route('appointments.store'), $this->appointmentPayload($patient, $doctor, null))
            ->assertRedirect(route('appointments.index'));

        $appointment = Appointment::where('patient_id', $patient->id)->firstOrFail();
        $payment = Payment::where('appointment_id', $appointment->id)->firstOrFail();

        $this->assertSame('0.00', $payment->amount);
        $this->assertStringContainsString('Monto pendiente por definir', (string) $payment->notes);
    }

    public function test_updating_pending_appointment_recalculates_payment_without_creating_duplicate(): void
    {
        [$clinic, $user] = $this->userWithRole('recepcionista');
        $patient = $this->patientForClinic($clinic);
        $doctor = $this->doctorForClinic($clinic, fee: 40);
        $service = $this->serviceForClinic($clinic, 'Consulta general', 25);
        $newService = $this->serviceForClinic($clinic, 'Consulta especializada', 80);

        $this->actingAs($user)->post(route('appointments.store'), $this->appointmentPayload($patient, $doctor, $service));
        $appointment = Appointment::where('patient_id', $patient->id)->firstOrFail();

        $this->actingAs($user)
            ->put(route('appointments.update', $appointment), $this->appointmentPayload($patient, $doctor, $newService, ['reason' => 'Control actualizado']))
            ->assertRedirect(route('appointments.show', $appointment));

        $this->assertSame(1, Payment::where('appointment_id', $appointment->id)->count());
        $this->assertDatabaseHas('payments', [
            'appointment_id' => $appointment->id,
            'service_id' => $newService->id,
            'amount' => 80,
            'payment_status' => 'pending',
        ]);
    }

    public function test_updating_appointment_does_not_modify_paid_payment(): void
    {
        [$clinic, $user] = $this->userWithRole('recepcionista');
        $patient = $this->patientForClinic($clinic);
        $doctor = $this->doctorForClinic($clinic, fee: 40);
        $service = $this->serviceForClinic($clinic, 'Consulta general', 25);
        $newService = $this->serviceForClinic($clinic, 'Consulta especializada', 80);

        $this->actingAs($user)->post(route('appointments.store'), $this->appointmentPayload($patient, $doctor, $service));
        $appointment = Appointment::where('patient_id', $patient->id)->firstOrFail();
        $payment = Payment::where('appointment_id', $appointment->id)->firstOrFail();
        $payment->update(['payment_status' => 'paid', 'payment_date' => '2026-08-01 08:00:00', 'amount' => 25]);

        $this->actingAs($user)
            ->put(route('appointments.update', $appointment), $this->appointmentPayload($patient, $doctor, $newService))
            ->assertRedirect(route('appointments.show', $appointment));

        $payment->refresh();
        $this->assertSame('paid', $payment->payment_status);
        $this->assertSame('25.00', $payment->amount);
        $this->assertSame($service->id, $payment->service_id);
    }

    public function test_cancelled_appointment_cancels_pending_payment_but_not_paid_payment(): void
    {
        [$clinic, $user] = $this->userWithRole('recepcionista');
        $patient = $this->patientForClinic($clinic, 'Paciente Pendiente');
        $paidPatient = $this->patientForClinic($clinic, 'Paciente Pagado');
        $doctor = $this->doctorForClinic($clinic);
        $service = $this->serviceForClinic($clinic);

        $this->actingAs($user)->post(route('appointments.store'), $this->appointmentPayload($patient, $doctor, $service, ['appointment_date' => '2026-08-01']));
        $pendingAppointment = Appointment::where('patient_id', $patient->id)->firstOrFail();
        $this->actingAs($user)->put(route('appointments.update', $pendingAppointment), $this->appointmentPayload($patient, $doctor, $service, ['appointment_date' => '2026-08-01', 'status' => 'cancelled']));

        $this->assertDatabaseHas('payments', ['appointment_id' => $pendingAppointment->id, 'payment_status' => 'cancelled']);

        $this->actingAs($user)->post(route('appointments.store'), $this->appointmentPayload($paidPatient, $doctor, $service, ['appointment_date' => '2026-08-02']));
        $paidAppointment = Appointment::where('patient_id', $paidPatient->id)->firstOrFail();
        Payment::where('appointment_id', $paidAppointment->id)->update(['payment_status' => 'paid', 'payment_date' => '2026-08-01 09:00:00']);

        $this->actingAs($user)->put(route('appointments.update', $paidAppointment), $this->appointmentPayload($paidPatient, $doctor, $service, ['appointment_date' => '2026-08-02', 'status' => 'cancelled']));

        $this->assertDatabaseHas('payments', ['appointment_id' => $paidAppointment->id, 'payment_status' => 'paid']);
    }

    public function test_no_show_appointment_leaves_pending_payment_pending(): void
    {
        [$clinic, $user] = $this->userWithRole('recepcionista');
        $patient = $this->patientForClinic($clinic);
        $doctor = $this->doctorForClinic($clinic);
        $service = $this->serviceForClinic($clinic);

        $this->actingAs($user)->post(route('appointments.store'), $this->appointmentPayload($patient, $doctor, $service));
        $appointment = Appointment::where('patient_id', $patient->id)->firstOrFail();

        $this->actingAs($user)
            ->put(route('appointments.update', $appointment), $this->appointmentPayload($patient, $doctor, $service, ['status' => 'no_show']))
            ->assertRedirect(route('appointments.show', $appointment));

        $this->assertDatabaseHas('payments', ['appointment_id' => $appointment->id, 'payment_status' => 'pending']);
    }

    public function test_cashier_sees_pending_collection_queue_and_can_collect_payment(): void
    {
        $this->travelTo('2026-08-15 10:00:00');
        [$clinic, $receptionist] = $this->userWithRole('recepcionista');
        [, $cashier] = $this->userWithRole('caja_finanzas', $clinic);
        $patient = $this->patientForClinic($clinic, 'Paciente Caja');
        $doctor = $this->doctorForClinic($clinic);
        $service = $this->serviceForClinic($clinic, price: 45);

        $this->actingAs($receptionist)->post(route('appointments.store'), $this->appointmentPayload($patient, $doctor, $service));
        $appointment = Appointment::where('patient_id', $patient->id)->firstOrFail();
        $payment = Payment::where('appointment_id', $appointment->id)->firstOrFail();

        $this->actingAs($cashier)
            ->get(route('payments.index'))
            ->assertOk()
            ->assertSee('Pendientes de cobro')
            ->assertSee('Paciente Caja')
            ->assertSee('Cobrar');

        $this->actingAs($cashier)
            ->put(route('payments.update', $payment), $this->paymentPayload($payment, ['payment_status' => 'paid', 'payment_date' => null, 'payment_method' => 'card']))
            ->assertRedirect(route('payments.show', $payment));

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'payment_status' => 'paid',
            'payment_method' => 'card',
            'payment_date' => '2026-08-15 10:00:00',
        ]);
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'confirmed']);
    }

    public function test_doctor_cannot_start_consultation_when_payment_is_pending_or_missing(): void
    {
        [$clinic, $doctorUser, $doctor] = $this->doctorUser();
        $patient = $this->patientForClinic($clinic, 'Paciente Pendiente');
        $pendingAppointment = $this->appointmentForClinic($clinic, $patient, $doctor);
        Payment::factory()->forAppointment($pendingAppointment)->create(['payment_status' => 'pending', 'payment_date' => null]);

        $this->actingAs($doctorUser)
            ->get(route('appointments.show', $pendingAppointment))
            ->assertOk()
            ->assertDontSee('Iniciar consulta')
            ->assertSee('pago no consta como pagado');

        $this->actingAs($doctorUser)
            ->get(route('consultations.create', ['appointment_id' => $pendingAppointment->id]))
            ->assertForbidden();

        $missingPaymentAppointment = $this->appointmentForClinic($clinic, $this->patientForClinic($clinic, 'Paciente Sinpago'), $doctor, null, '2026-08-03', '11:00');

        $this->actingAs($doctorUser)
            ->get(route('consultations.create', ['appointment_id' => $missingPaymentAppointment->id]))
            ->assertForbidden();
    }

    public function test_doctor_can_start_and_store_consultation_when_payment_is_paid(): void
    {
        [$clinic, $doctorUser, $doctor] = $this->doctorUser();
        $patient = $this->patientForClinic($clinic, 'Paciente Pagado');
        $appointment = $this->appointmentForClinic($clinic, $patient, $doctor);
        Payment::factory()->forAppointment($appointment)->create(['payment_status' => 'paid', 'payment_date' => '2026-08-01 09:30:00']);

        $this->actingAs($doctorUser)
            ->get(route('consultations.create', ['appointment_id' => $appointment->id]))
            ->assertOk()
            ->assertSee('value="'.$appointment->id.'" selected', false);

        $this->actingAs($doctorUser)
            ->post(route('consultations.store'), $this->consultationPayload($patient, $doctor, $appointment))
            ->assertRedirect(route('consultations.index'));

        $this->assertDatabaseHas('consultations', ['appointment_id' => $appointment->id, 'patient_id' => $patient->id, 'doctor_id' => $doctor->id]);
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'completed']);
    }

    public function test_appointment_show_displays_financial_state(): void
    {
        [$clinic, $user] = $this->userWithRole('recepcionista');
        $patient = $this->patientForClinic($clinic, 'Paciente Financiero');
        $doctor = $this->doctorForClinic($clinic);
        $appointment = $this->appointmentForClinic($clinic, $patient, $doctor);
        Payment::factory()->forAppointment($appointment)->create(['amount' => 30, 'payment_status' => 'pending']);

        $this->actingAs($user)
            ->get(route('appointments.show', $appointment))
            ->assertOk()
            ->assertSee('Estado financiero')
            ->assertSee('$30.00')
            ->assertSee('Pendiente')
            ->assertSee('Por definir al cobrar');
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

    private function patientForClinic(Clinic $clinic, string $name = 'Paciente Test'): Patient
    {
        [$first, $last] = array_pad(explode(' ', $name, 2), 2, 'Test');

        return Patient::factory()->for($clinic)->create(['first_name' => $first, 'last_name' => $last]);
    }

    private function doctorForClinic(Clinic $clinic, string $name = 'Doctor Test', float $fee = 50): Doctor
    {
        $user = User::factory()->create(['clinic_id' => $clinic->id, 'name' => $name]);

        return Doctor::factory()
            ->for($clinic)
            ->for($user)
            ->for(Specialty::factory()->create())
            ->create(['status' => 'active', 'consultation_fee' => $fee]);
    }

    private function serviceForClinic(Clinic $clinic, string $name = 'Consulta general', float $price = 25): Service
    {
        return Service::factory()->for($clinic)->create(['name' => $name, 'price' => $price, 'duration_minutes' => 30, 'status' => 'active']);
    }

    private function appointmentForClinic(Clinic $clinic, ?Patient $patient = null, ?Doctor $doctor = null, ?Service $service = null, string $date = '2026-08-01', string $time = '09:00', string $status = 'scheduled'): Appointment
    {
        $patient ??= $this->patientForClinic($clinic);
        $doctor ??= $this->doctorForClinic($clinic);

        if ($service) {
            $doctor->services()->syncWithoutDetaching([$service->id]);
        }

        return Appointment::factory()->for($clinic)->for($patient)->for($doctor)->create([
            'service_id' => $service?->id,
            'appointment_date' => $date,
            'start_time' => $time,
            'end_time' => null,
            'reason' => 'Consulta de prueba',
            'status' => $status,
        ]);
    }

    private function appointmentPayload(Patient $patient, Doctor $doctor, ?Service $service, array $overrides = []): array
    {
        if ($service) {
            $doctor->services()->syncWithoutDetaching([$service->id]);
        }

        return array_merge([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'service_id' => $service?->id,
            'appointment_date' => '2026-08-01',
            'start_time' => '09:00',
            'end_time' => null,
            'reason' => 'Consulta de prueba',
            'status' => 'scheduled',
            'notes' => null,
        ], $overrides);
    }

    private function paymentPayload(Payment $payment, array $overrides = []): array
    {
        return array_merge([
            'patient_id' => $payment->patient_id,
            'appointment_id' => $payment->appointment_id,
            'service_id' => $payment->service_id,
            'amount' => $payment->amount,
            'payment_method' => $payment->payment_method,
            'payment_status' => $payment->payment_status,
            'payment_date' => $payment->payment_date?->format('Y-m-d H:i:s'),
            'notes' => $payment->notes,
        ], $overrides);
    }

    private function consultationPayload(Patient $patient, Doctor $doctor, Appointment $appointment): array
    {
        return [
            'appointment_id' => $appointment->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'reason' => 'Dolor abdominal',
            'symptoms' => 'Dolor y náuseas',
            'diagnosis' => 'Gastroenteritis',
            'treatment' => 'Hidratación y reposo',
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
