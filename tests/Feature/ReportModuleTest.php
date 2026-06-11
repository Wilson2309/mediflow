<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\Service;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_reports(): void
    {
        foreach ($this->reportRoutes() as $routeName) {
            $this->get(route($routeName))->assertRedirect(route('login', absolute: false));
        }
    }

    public function test_authenticated_user_can_access_general_report(): void
    {
        $user = $this->userForClinic(Clinic::factory()->create());

        $this->actingAs($user)->get(route('reports.index'))->assertOk()->assertSee('Resumen ejecutivo');
    }

    public function test_authenticated_user_can_access_each_report_section(): void
    {
        $user = $this->userForClinic(Clinic::factory()->create());

        foreach (array_slice($this->reportRoutes(), 1) as $routeName) {
            $this->actingAs($user)->get(route($routeName))->assertOk();
        }
    }

    public function test_reports_only_show_data_from_authenticated_users_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $ownAppointment = $this->appointmentForClinic($clinic, patientName: 'Paciente Visible');
        $otherAppointment = $this->appointmentForClinic($otherClinic, patientName: 'Paciente Oculto');

        $this->actingAs($this->userForClinic($clinic))
            ->get(route('reports.appointments', $this->currentPeriod()))
            ->assertOk()
            ->assertSee($ownAppointment->patient->full_name)
            ->assertDontSee($otherAppointment->patient->full_name);
    }

    public function test_general_report_calculates_real_metrics(): void
    {
        $clinic = Clinic::factory()->create();
        $patient = $this->patientForClinic($clinic);
        $doctor = $this->doctorForClinic($clinic);
        Service::factory()->for($clinic)->create(['status' => 'active']);
        $appointment = $this->appointmentForClinic($clinic, $patient, $doctor, status: 'completed');
        Consultation::factory()->create(['patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'consultation_date' => now()]);
        Prescription::factory()->create(['patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'prescription_date' => today()]);
        Payment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'service_id' => $appointment->service_id,
            'amount' => 80,
            'payment_status' => 'paid',
            'payment_date' => now(),
        ]);

        $this->actingAs($this->userForClinic($clinic))
            ->get(route('reports.index', $this->currentPeriod()))
            ->assertOk()
            ->assertViewHas('metrics', fn ($metrics) => $metrics['activePatients'] === 1
                && $metrics['activeDoctors'] === 1
                && $metrics['appointments'] === 1
                && $metrics['completedAppointments'] === 1
                && $metrics['consultations'] === 1
                && $metrics['prescriptions'] === 1
                && $metrics['paidIncome'] === 80.0);
    }

    public function test_appointments_report_filters_by_date_range(): void
    {
        $clinic = Clinic::factory()->create();
        $inside = $this->appointmentForClinic($clinic, patientName: 'Paciente Dentro');
        $outside = $this->appointmentForClinic($clinic, patientName: 'Paciente Fuera', date: now()->subMonths(2)->toDateString());

        $this->actingAs($this->userForClinic($clinic))
            ->get(route('reports.appointments', $this->currentPeriod()))
            ->assertOk()->assertSee($inside->patient->full_name)->assertDontSee($outside->patient->full_name);
    }

    public function test_appointments_report_filters_by_status(): void
    {
        $clinic = Clinic::factory()->create();
        $completed = $this->appointmentForClinic($clinic, patientName: 'Paciente Completado', status: 'completed');
        $cancelled = $this->appointmentForClinic($clinic, patientName: 'Paciente Cancelado', status: 'cancelled');

        $this->actingAs($this->userForClinic($clinic))
            ->get(route('reports.appointments', [...$this->currentPeriod(), 'status' => 'completed']))
            ->assertOk()->assertSee($completed->patient->full_name)->assertDontSee($cancelled->patient->full_name);
    }

    public function test_appointments_report_filters_by_doctor(): void
    {
        $clinic = Clinic::factory()->create();
        $doctor = $this->doctorForClinic($clinic, 'Doctora Seleccionada');
        $otherDoctor = $this->doctorForClinic($clinic, 'Doctor Oculto');
        $matching = $this->appointmentForClinic($clinic, doctor: $doctor, patientName: 'Paciente Seleccionado');
        $other = $this->appointmentForClinic($clinic, doctor: $otherDoctor, patientName: 'Paciente No Seleccionado');

        $this->actingAs($this->userForClinic($clinic))
            ->get(route('reports.appointments', [...$this->currentPeriod(), 'doctor_id' => $doctor->id]))
            ->assertOk()->assertSee($matching->patient->full_name)->assertDontSee($other->patient->full_name);
    }

    public function test_financial_report_only_counts_paid_payments_as_income(): void
    {
        $clinic = Clinic::factory()->create();
        $patient = $this->patientForClinic($clinic);
        $service = Service::factory()->for($clinic)->create();
        $this->paymentForClinic($clinic, $patient, $service, 'paid', 'cash', 100);
        $this->paymentForClinic($clinic, $patient, $service, 'pending', 'cash', 900);

        $this->actingAs($this->userForClinic($clinic))
            ->get(route('reports.financial', $this->currentPeriod()))
            ->assertOk()
            ->assertViewHas('metrics', fn ($metrics) => $metrics['paidIncome'] === 100.0 && $metrics['pending'] === 1);
    }

    public function test_financial_report_filters_by_payment_method(): void
    {
        $clinic = Clinic::factory()->create();
        $patient = $this->patientForClinic($clinic);
        $service = Service::factory()->for($clinic)->create();
        $cash = $this->paymentForClinic($clinic, $patient, $service, 'paid', 'cash', 20, 'Pago efectivo visible');
        $card = $this->paymentForClinic($clinic, $patient, $service, 'paid', 'card', 30, 'Pago tarjeta oculto');

        $this->actingAs($this->userForClinic($clinic))
            ->get(route('reports.financial', [...$this->currentPeriod(), 'payment_method' => 'cash']))
            ->assertOk()->assertSee($cash->notes)->assertDontSee($card->notes);
    }

    public function test_financial_report_filters_by_payment_status(): void
    {
        $clinic = Clinic::factory()->create();
        $patient = $this->patientForClinic($clinic);
        $service = Service::factory()->for($clinic)->create();
        $paid = $this->paymentForClinic($clinic, $patient, $service, 'paid', 'cash', 20, 'Pago pagado visible');
        $pending = $this->paymentForClinic($clinic, $patient, $service, 'pending', 'cash', 30, 'Pago pendiente oculto');

        $this->actingAs($this->userForClinic($clinic))
            ->get(route('reports.financial', [...$this->currentPeriod(), 'payment_status' => 'paid']))
            ->assertOk()->assertSee($paid->notes)->assertDontSee($pending->notes);
    }

    public function test_patients_report_filters_by_status(): void
    {
        $clinic = Clinic::factory()->create();
        $active = $this->patientForClinic($clinic, 'Paciente Activo', 'active');
        $inactive = $this->patientForClinic($clinic, 'Paciente Inactivo', 'inactive');

        $this->actingAs($this->userForClinic($clinic))
            ->get(route('reports.patients', [...$this->currentPeriod(), 'status' => 'active']))
            ->assertOk()
            ->assertViewHas('patients', fn ($patients) => $patients->contains('id', $active->id) && ! $patients->contains('id', $inactive->id));
    }

    public function test_doctors_report_filters_by_status(): void
    {
        $clinic = Clinic::factory()->create();
        $active = $this->doctorForClinic($clinic, 'Doctora Activa', 'active');
        $inactive = $this->doctorForClinic($clinic, 'Doctor Inactivo', 'inactive');

        $this->actingAs($this->userForClinic($clinic))
            ->get(route('reports.doctors', [...$this->currentPeriod(), 'status' => 'active']))
            ->assertOk()->assertSee($active->user->name)->assertDontSee($inactive->user->name);
    }

    public function test_doctors_report_filters_by_specialty(): void
    {
        $clinic = Clinic::factory()->create();
        $selectedSpecialty = Specialty::factory()->create(['name' => 'Cardiología de prueba']);
        $otherSpecialty = Specialty::factory()->create(['name' => 'Pediatría de prueba']);
        $matching = $this->doctorForClinic($clinic, 'Doctor Cardiólogo', specialty: $selectedSpecialty);
        $other = $this->doctorForClinic($clinic, 'Doctor Pediatra', specialty: $otherSpecialty);

        $this->actingAs($this->userForClinic($clinic))
            ->get(route('reports.doctors', [...$this->currentPeriod(), 'specialty_id' => $selectedSpecialty->id]))
            ->assertOk()->assertSee($matching->user->name)->assertDontSee($other->user->name);
    }

    public function test_services_report_filters_by_status(): void
    {
        $clinic = Clinic::factory()->create();
        $active = Service::factory()->for($clinic)->create(['name' => 'Servicio Activo Visible', 'status' => 'active']);
        $inactive = Service::factory()->for($clinic)->create(['name' => 'Servicio Inactivo Oculto', 'status' => 'inactive']);

        $this->actingAs($this->userForClinic($clinic))
            ->get(route('reports.services', [...$this->currentPeriod(), 'status' => 'active']))
            ->assertOk()
            ->assertViewHas('servicesReport', fn ($services) => $services->contains('id', $active->id) && ! $services->contains('id', $inactive->id));
    }

    public function test_clinical_report_shows_only_consultations_and_prescriptions_from_own_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $patient = $this->patientForClinic($clinic, 'Paciente Clínico Visible');
        $doctor = $this->doctorForClinic($clinic, 'Doctora Clínica Visible');
        $otherPatient = $this->patientForClinic($otherClinic, 'Paciente Clínico Oculto');
        $otherDoctor = $this->doctorForClinic($otherClinic, 'Doctor Clínico Oculto');
        Consultation::factory()->create(['patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'consultation_date' => now(), 'diagnosis' => 'Diagnóstico visible']);
        Prescription::factory()->create(['patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'prescription_date' => today(), 'general_instructions' => 'Receta visible']);
        Consultation::factory()->create(['patient_id' => $otherPatient->id, 'doctor_id' => $otherDoctor->id, 'consultation_date' => now(), 'diagnosis' => 'Diagnóstico oculto']);
        Prescription::factory()->create(['patient_id' => $otherPatient->id, 'doctor_id' => $otherDoctor->id, 'prescription_date' => today(), 'general_instructions' => 'Receta oculta']);

        $this->actingAs($this->userForClinic($clinic))
            ->get(route('reports.clinical', $this->currentPeriod()))
            ->assertOk()->assertSee('Diagnóstico visible')->assertSee('Receta visible')->assertDontSee('Diagnóstico oculto')->assertDontSee('Receta oculta');
    }

    public function test_invalid_filters_return_validation_errors_without_server_error(): void
    {
        $user = $this->userForClinic(Clinic::factory()->create());

        $this->actingAs($user)
            ->from(route('reports.appointments'))
            ->get(route('reports.appointments', ['start_date' => '2026-06-20', 'end_date' => '2026-06-01', 'status' => 'invalid']))
            ->assertRedirect(route('reports.appointments'))
            ->assertSessionHasErrors(['end_date', 'status']);
    }

    public function test_foreign_clinic_filter_ids_are_rejected(): void
    {
        $clinic = Clinic::factory()->create();
        $otherDoctor = $this->doctorForClinic(Clinic::factory()->create());

        $this->actingAs($this->userForClinic($clinic))
            ->from(route('reports.appointments'))
            ->get(route('reports.appointments', ['doctor_id' => $otherDoctor->id]))
            ->assertRedirect(route('reports.appointments'))
            ->assertSessionHasErrors('doctor_id');
    }

    public function test_dashboard_and_sidebar_remain_available(): void
    {
        $user = $this->userForClinic(Clinic::factory()->create());

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Reportes');
        $this->actingAs($user)->get(route('reports.index'))->assertOk()->assertSee(route('reports.index'), escape: false);
    }

    /** @return array<int, string> */
    private function reportRoutes(): array
    {
        return ['reports.index', 'reports.appointments', 'reports.clinical', 'reports.financial', 'reports.patients', 'reports.doctors', 'reports.services'];
    }

    private function userForClinic(Clinic $clinic): User
    {
        return User::factory()->create(['clinic_id' => $clinic->id]);
    }

    private function patientForClinic(Clinic $clinic, string $name = 'Paciente Prueba', string $status = 'active'): Patient
    {
        [$firstName, $lastName] = array_pad(explode(' ', $name, 2), 2, 'Prueba');

        return Patient::factory()->for($clinic)->create(['first_name' => $firstName, 'last_name' => $lastName, 'status' => $status]);
    }

    private function doctorForClinic(Clinic $clinic, string $name = 'Doctor Prueba', string $status = 'active', ?Specialty $specialty = null): Doctor
    {
        $user = $this->userForClinic($clinic);
        $user->update(['name' => $name]);

        return Doctor::factory()->for($clinic)->create([
            'user_id' => $user->id,
            'specialty_id' => ($specialty ?? Specialty::factory()->create())->id,
            'status' => $status,
        ]);
    }

    private function appointmentForClinic(Clinic $clinic, ?Patient $patient = null, ?Doctor $doctor = null, string $status = 'scheduled', ?string $patientName = null, ?string $date = null): Appointment
    {
        $patient ??= $this->patientForClinic($clinic, $patientName ?? 'Paciente Cita');
        $doctor ??= $this->doctorForClinic($clinic);
        $service = Service::factory()->for($clinic)->create();

        return Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'service_id' => $service->id,
            'appointment_date' => $date ?? today()->toDateString(),
            'status' => $status,
        ]);
    }

    private function paymentForClinic(Clinic $clinic, Patient $patient, Service $service, string $status, string $method, float $amount, string $notes = 'Pago de prueba'): Payment
    {
        return Payment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'service_id' => $service->id,
            'amount' => $amount,
            'payment_status' => $status,
            'payment_method' => $method,
            'payment_date' => $status === 'pending' ? null : now(),
            'notes' => $notes,
        ]);
    }

    /** @return array{start_date: string, end_date: string} */
    private function currentPeriod(): array
    {
        return ['start_date' => now()->startOfMonth()->toDateString(), 'end_date' => now()->endOfMonth()->toDateString()];
    }
}
