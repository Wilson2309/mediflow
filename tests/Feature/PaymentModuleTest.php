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
use Tests\TestCase;

class PaymentModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_payments_index(): void
    {
        $this->get(route('payments.index'))->assertRedirect(route('login', absolute: false));
    }

    public function test_authenticated_user_can_see_payments_from_own_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $ownPayment = $this->paymentForClinic(
            $clinic,
            patient: $this->patientForClinic($clinic, 'Paciente Visible')
        );
        $otherPayment = $this->paymentForClinic(
            $otherClinic,
            patient: $this->patientForClinic($otherClinic, 'Paciente Oculto')
        );

        $this->actingAs($user)
            ->get(route('payments.index'))
            ->assertOk()
            ->assertSee($ownPayment->patient->full_name)
            ->assertDontSee($otherPayment->patient->full_name);
    }

    public function test_authenticated_user_can_open_create_payment_form(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $this->patientForClinic($clinic);
        $this->appointmentForClinic($clinic);
        $this->serviceForClinic($clinic);

        $this->actingAs($user)
            ->get(route('payments.create'))
            ->assertOk()
            ->assertSee('Nuevo pago');
    }

    public function test_authenticated_user_can_create_valid_payment(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        [$patient, $appointment, $service] = $this->relatedRecords($clinic);

        $this->actingAs($user)
            ->post(route('payments.store'), $this->validPayload($patient, $appointment, $service, ['notes' => 'Pago consulta']))
            ->assertRedirect(route('payments.index'))
            ->assertSessionHas('success', 'Pago creado correctamente.');

        $this->assertDatabaseHas('payments', [
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'notes' => 'Pago consulta',
        ]);
    }

    public function test_creating_payment_assigns_clinic_id_automatically(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        [$patient, $appointment, $service] = $this->relatedRecords($clinic);

        $this->actingAs($user)
            ->post(route('payments.store'), array_merge($this->validPayload($patient, $appointment, $service), ['clinic_id' => Clinic::factory()->create()->id]))
            ->assertRedirect(route('payments.index'));

        $this->assertDatabaseHas('payments', ['clinic_id' => $clinic->id, 'patient_id' => $patient->id]);
    }

    public function test_paid_payment_without_payment_date_gets_current_date(): void
    {
        $this->travelTo('2026-07-20 14:30:00');
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        [$patient, $appointment, $service] = $this->relatedRecords($clinic);

        $this->actingAs($user)
            ->post(route('payments.store'), $this->validPayload($patient, $appointment, $service, ['payment_status' => 'paid', 'payment_date' => null]))
            ->assertRedirect(route('payments.index'));

        $this->assertDatabaseHas('payments', ['patient_id' => $patient->id, 'payment_status' => 'paid', 'payment_date' => '2026-07-20 14:30:00']);
    }

    public function test_payment_cannot_be_created_without_patient_id(): void
    {
        [$user, $patient, $appointment, $service] = $this->setupForValidation();

        $this->actingAs($user)
            ->from(route('payments.create'))
            ->post(route('payments.store'), $this->validPayload($patient, $appointment, $service, ['patient_id' => '']))
            ->assertRedirect(route('payments.create'))
            ->assertSessionHasErrors('patient_id');
    }

    public function test_payment_cannot_be_created_without_amount(): void
    {
        [$user, $patient, $appointment, $service] = $this->setupForValidation();

        $this->actingAs($user)
            ->from(route('payments.create'))
            ->post(route('payments.store'), $this->validPayload($patient, $appointment, $service, ['amount' => '']))
            ->assertRedirect(route('payments.create'))
            ->assertSessionHasErrors('amount');
    }

    public function test_payment_cannot_be_created_with_amount_zero_or_negative(): void
    {
        [$user, $patient, $appointment, $service] = $this->setupForValidation();

        $this->actingAs($user)
            ->from(route('payments.create'))
            ->post(route('payments.store'), $this->validPayload($patient, $appointment, $service, ['amount' => 0]))
            ->assertRedirect(route('payments.create'))
            ->assertSessionHasErrors('amount');
    }

    public function test_payment_cannot_be_created_with_invalid_payment_method(): void
    {
        [$user, $patient, $appointment, $service] = $this->setupForValidation();

        $this->actingAs($user)
            ->from(route('payments.create'))
            ->post(route('payments.store'), $this->validPayload($patient, $appointment, $service, ['payment_method' => 'efectivo']))
            ->assertRedirect(route('payments.create'))
            ->assertSessionHasErrors('payment_method');
    }

    public function test_payment_cannot_be_created_with_invalid_payment_status(): void
    {
        [$user, $patient, $appointment, $service] = $this->setupForValidation();

        $this->actingAs($user)
            ->from(route('payments.create'))
            ->post(route('payments.store'), $this->validPayload($patient, $appointment, $service, ['payment_status' => 'pagado']))
            ->assertRedirect(route('payments.create'))
            ->assertSessionHasErrors('payment_status');
    }

    public function test_payment_cannot_be_created_with_patient_from_other_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        [$patient, $appointment, $service] = $this->relatedRecords($clinic);
        $otherPatient = $this->patientForClinic($otherClinic);

        $this->actingAs($user)
            ->from(route('payments.create'))
            ->post(route('payments.store'), $this->validPayload($patient, $appointment, $service, ['patient_id' => $otherPatient->id]))
            ->assertRedirect(route('payments.create'))
            ->assertSessionHasErrors('clinic_id');
    }

    public function test_payment_cannot_be_created_with_appointment_from_other_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        [$patient, $appointment, $service] = $this->relatedRecords($clinic);
        $otherAppointment = $this->appointmentForClinic($otherClinic);

        $this->actingAs($user)
            ->from(route('payments.create'))
            ->post(route('payments.store'), $this->validPayload($patient, $appointment, $service, ['appointment_id' => $otherAppointment->id]))
            ->assertRedirect(route('payments.create'))
            ->assertSessionHasErrors('clinic_id');
    }

    public function test_payment_cannot_be_created_with_service_from_other_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        [$patient, $appointment, $service] = $this->relatedRecords($clinic);
        $otherService = $this->serviceForClinic($otherClinic);

        $this->actingAs($user)
            ->from(route('payments.create'))
            ->post(route('payments.store'), $this->validPayload($patient, $appointment, $service, ['service_id' => $otherService->id]))
            ->assertRedirect(route('payments.create'))
            ->assertSessionHasErrors('clinic_id');
    }

    public function test_payment_cannot_be_created_when_appointment_does_not_match_patient(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        [$patient, $appointment, $service] = $this->relatedRecords($clinic);
        $otherPatient = $this->patientForClinic($clinic, 'Paciente Diferente');

        $this->actingAs($user)
            ->from(route('payments.create'))
            ->post(route('payments.store'), $this->validPayload($otherPatient, $appointment, $service))
            ->assertRedirect(route('payments.create'))
            ->assertSessionHasErrors('appointment_id');
    }

    public function test_authenticated_user_can_view_payment_from_own_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $payment = $this->paymentForClinic($clinic, notes: 'Vista pago');

        $this->actingAs($user)->get(route('payments.show', $payment))->assertOk()->assertSee('Vista pago');
    }

    public function test_authenticated_user_can_edit_payment_from_own_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $payment = $this->paymentForClinic($clinic);

        $this->actingAs($user)->get(route('payments.edit', $payment))->assertOk()->assertSee('Editar pago');
    }

    public function test_authenticated_user_can_update_payment_from_own_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        [$patient, $appointment, $service] = $this->relatedRecords($clinic);
        $payment = $this->paymentForClinic($clinic, $patient, $appointment, $service);

        $this->actingAs($user)
            ->put(route('payments.update', $payment), $this->validPayload($patient, $appointment, $service, ['amount' => '80.00', 'notes' => 'Pago actualizado']))
            ->assertRedirect(route('payments.show', $payment))
            ->assertSessionHas('success', 'Pago actualizado correctamente.');

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'amount' => '80.00', 'notes' => 'Pago actualizado']);
    }

    public function test_authenticated_user_can_delete_payment_from_own_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $payment = $this->paymentForClinic($clinic);

        $this->actingAs($user)
            ->delete(route('payments.destroy', $payment))
            ->assertRedirect(route('payments.index'))
            ->assertSessionHas('success', 'Pago eliminado correctamente.');

        $this->assertDatabaseMissing('payments', ['id' => $payment->id]);
    }

    public function test_search_by_patient_works(): void
    {
        [$user, $match, $other] = $this->twoPayments('Paciente Buscable', 'Paciente Oculto');

        $this->actingAs($user)->get(route('payments.index', ['search' => 'Buscable']))->assertOk()->assertSee('Paciente Buscable')->assertDontSee('Paciente Oculto');
    }

    public function test_search_by_service_works(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $match = $this->paymentForClinic($clinic, service: $this->serviceForClinic($clinic, 'Servicio Buscable'));
        $other = $this->paymentForClinic($clinic, service: $this->serviceForClinic($clinic, 'Servicio Oculto'));

        $this->actingAs($user)->get(route('payments.index', ['search' => 'Buscable']))->assertOk()->assertSee('Servicio Buscable')->assertDontSee('Servicio Oculto');
    }

    public function test_filter_by_pending_status_works(): void
    {
        [$user, $match, $other] = $this->twoPaymentsByStatus('pending', 'paid');

        $this->actingAs($user)->get(route('payments.index', ['payment_status' => 'pending']))->assertOk()->assertSee($match->patient->full_name)->assertDontSee($other->patient->full_name);
    }

    public function test_filter_by_paid_status_works(): void
    {
        [$user, $match, $other] = $this->twoPaymentsByStatus('paid', 'pending');

        $this->actingAs($user)->get(route('payments.index', ['payment_status' => 'paid']))->assertOk()->assertSee($match->patient->full_name)->assertDontSee($other->patient->full_name);
    }

    public function test_filter_by_cash_method_works(): void
    {
        [$user, $match, $other] = $this->twoPaymentsByMethod('cash', 'card');

        $this->actingAs($user)->get(route('payments.index', ['payment_method' => 'cash']))->assertOk()->assertSee($match->patient->full_name)->assertDontSee($other->patient->full_name);
    }

    public function test_filter_by_card_method_works(): void
    {
        [$user, $match, $other] = $this->twoPaymentsByMethod('card', 'cash');

        $this->actingAs($user)->get(route('payments.index', ['payment_method' => 'card']))->assertOk()->assertSee($match->patient->full_name)->assertDontSee($other->patient->full_name);
    }

    public function test_filter_by_date_range_works(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $match = $this->paymentForClinic(
            $clinic,
            patient: $this->patientForClinic($clinic, 'Paciente Fecha Uno'),
            date: '2026-08-10 10:00:00'
        );
        $other = $this->paymentForClinic(
            $clinic,
            patient: $this->patientForClinic($clinic, 'Paciente Fecha Dos'),
            date: '2026-08-20 10:00:00'
        );

        $this->actingAs($user)
            ->get(route('payments.index', ['date_from' => '2026-08-01', 'date_to' => '2026-08-15']))
            ->assertOk()
            ->assertSee($match->patient->full_name)
            ->assertDontSee($other->patient->full_name);
    }

    public function test_user_cannot_view_payment_from_other_clinic(): void
    {
        [$user, $payment] = $this->userAndOtherClinicPayment();
        $this->actingAs($user)->get(route('payments.show', $payment))->assertForbidden();
    }

    public function test_user_cannot_edit_payment_from_other_clinic(): void
    {
        [$user, $payment] = $this->userAndOtherClinicPayment();
        $this->actingAs($user)->get(route('payments.edit', $payment))->assertForbidden();
    }

    public function test_user_cannot_update_payment_from_other_clinic(): void
    {
        [$user, $payment] = $this->userAndOtherClinicPayment();

        $this->actingAs($user)
            ->put(route('payments.update', $payment), $this->validPayload($payment->patient, $payment->appointment, $payment->service))
            ->assertForbidden();
    }

    public function test_user_cannot_delete_payment_from_other_clinic(): void
    {
        [$user, $payment] = $this->userAndOtherClinicPayment();

        $this->actingAs($user)->delete(route('payments.destroy', $payment))->assertForbidden();
        $this->assertDatabaseHas('payments', ['id' => $payment->id]);
    }

    public function test_dashboard_shows_real_monthly_income(): void
    {
        $this->travelTo('2026-08-12 12:00:00');
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $this->paymentForClinic($clinic, amount: 120, status: 'paid', date: '2026-08-01 10:00:00');
        $this->paymentForClinic($clinic, amount: 30, status: 'paid', date: '2026-08-08 10:00:00');
        $this->paymentForClinic($clinic, amount: 99, status: 'paid', date: '2026-07-08 10:00:00');

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('$150.00');
    }

    public function test_dashboard_shows_real_pending_payments(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $this->paymentForClinic($clinic, status: 'pending');
        $this->paymentForClinic($clinic, status: 'pending');
        $this->paymentForClinic($clinic, status: 'paid');

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Pagos pendientes')->assertSee('2');
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

    private function serviceForClinic(Clinic $clinic, string $name = 'Consulta general'): Service
    {
        return Service::factory()->for($clinic)->create(['name' => $name, 'price' => 25, 'duration_minutes' => 30]);
    }

    private function appointmentForClinic(Clinic $clinic, ?Patient $patient = null, ?Doctor $doctor = null, ?Service $service = null): Appointment
    {
        $patient ??= $this->patientForClinic($clinic);
        $doctor ??= $this->doctorForClinic($clinic);
        $service ??= $this->serviceForClinic($clinic);

        return Appointment::factory()->for($clinic)->for($patient)->for($doctor)->for($service)->create([
            'appointment_date' => '2026-08-10',
            'start_time' => '09:00',
            'status' => 'completed',
        ]);
    }

    private function relatedRecords(Clinic $clinic): array
    {
        $patient = $this->patientForClinic($clinic);
        $doctor = $this->doctorForClinic($clinic);
        $service = $this->serviceForClinic($clinic);
        $appointment = $this->appointmentForClinic($clinic, $patient, $doctor, $service);

        return [$patient, $appointment, $service];
    }

    private function paymentForClinic(
        Clinic $clinic,
        ?Patient $patient = null,
        ?Appointment $appointment = null,
        ?Service $service = null,
        string $status = 'paid',
        string $method = 'cash',
        string $date = '2026-08-10 10:00:00',
        string $notes = 'Pago de prueba',
        float $amount = 50
    ): Payment {
        $patient ??= $this->patientForClinic($clinic);
        $service ??= $this->serviceForClinic($clinic);
        $appointment ??= $this->appointmentForClinic($clinic, $patient, null, $service);

        return Payment::factory()->for($clinic)->for($patient)->for($appointment)->for($service)->create([
            'amount' => $amount,
            'payment_method' => $method,
            'payment_status' => $status,
            'payment_date' => $status === 'paid' ? $date : null,
            'notes' => $notes,
        ]);
    }

    private function validPayload(Patient $patient, ?Appointment $appointment, ?Service $service, array $overrides = []): array
    {
        return array_merge([
            'patient_id' => $patient->id,
            'appointment_id' => $appointment?->id,
            'service_id' => $service?->id,
            'amount' => '45.00',
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'payment_date' => '2026-08-10 10:00:00',
            'notes' => 'Pago registrado',
        ], $overrides);
    }

    private function setupForValidation(): array
    {
        $clinic = Clinic::factory()->create();
        return [$this->userForClinic($clinic), ...$this->relatedRecords($clinic)];
    }

    private function twoPayments(string $matchingPatient, string $otherPatient): array
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $match = $this->paymentForClinic($clinic, patient: $this->patientForClinic($clinic, $matchingPatient));
        $other = $this->paymentForClinic($clinic, patient: $this->patientForClinic($clinic, $otherPatient));
        return [$user, $match, $other];
    }

    private function twoPaymentsByStatus(string $matchingStatus, string $otherStatus): array
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $match = $this->paymentForClinic($clinic, patient: $this->patientForClinic($clinic, 'Paciente Estado Uno'), status: $matchingStatus);
        $other = $this->paymentForClinic($clinic, patient: $this->patientForClinic($clinic, 'Paciente Estado Dos'), status: $otherStatus);
        return [$user, $match, $other];
    }

    private function twoPaymentsByMethod(string $matchingMethod, string $otherMethod): array
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userForClinic($clinic);
        $match = $this->paymentForClinic($clinic, patient: $this->patientForClinic($clinic, 'Paciente Metodo Uno'), method: $matchingMethod);
        $other = $this->paymentForClinic($clinic, patient: $this->patientForClinic($clinic, 'Paciente Metodo Dos'), method: $otherMethod);
        return [$user, $match, $other];
    }

    private function userAndOtherClinicPayment(): array
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        return [$this->userForClinic($clinic), $this->paymentForClinic($otherClinic)];
    }
}
