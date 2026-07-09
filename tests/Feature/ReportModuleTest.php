<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AuditLog;
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
        $service = Service::factory()->for($clinic)->create();
        $cashPatient = $this->patientForClinic($clinic, 'Paciente Efectivo Visible');
        $cardPatient = $this->patientForClinic($clinic, 'Paciente Tarjeta Oculto');
        $cash = $this->paymentForClinic($clinic, $cashPatient, $service, 'paid', 'cash', 20, 'Pago efectivo visible');
        $card = $this->paymentForClinic($clinic, $cardPatient, $service, 'paid', 'card', 30, 'Pago tarjeta oculto');

        $this->actingAs($this->userForClinic($clinic))
            ->get(route('reports.financial', [...$this->currentPeriod(), 'payment_method' => 'cash']))
            ->assertOk()->assertSee($cash->patient->full_name)->assertDontSee($card->patient->full_name);
    }

    public function test_financial_report_filters_by_payment_status(): void
    {
        $clinic = Clinic::factory()->create();
        $service = Service::factory()->for($clinic)->create();
        $paidPatient = $this->patientForClinic($clinic, 'Paciente Pagado Visible');
        $pendingPatient = $this->patientForClinic($clinic, 'Paciente Pendiente Oculto');
        $paid = $this->paymentForClinic($clinic, $paidPatient, $service, 'paid', 'cash', 20, 'Pago pagado visible');
        $pending = $this->paymentForClinic($clinic, $pendingPatient, $service, 'pending', 'cash', 30, 'Pago pendiente oculto');

        $this->actingAs($this->userForClinic($clinic))
            ->get(route('reports.financial', [...$this->currentPeriod(), 'payment_status' => 'paid']))
            ->assertOk()->assertSee($paid->patient->full_name)->assertDontSee($pending->patient->full_name);
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

    public function test_admin_sees_all_report_sections(): void
    {
        $user = $this->userWithRole('administrador');

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee(route('reports.index'), false)
            ->assertSee(route('reports.appointments'), false)
            ->assertSee(route('reports.clinical'), false)
            ->assertSee(route('reports.financial'), false)
            ->assertSee(route('reports.patients'), false)
            ->assertSee(route('reports.doctors'), false)
            ->assertSee(route('reports.services'), false);
    }

    public function test_cashier_is_redirected_to_financial_report_and_only_sees_financial_navigation(): void
    {
        $user = $this->userWithRole('caja_finanzas');

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertRedirect(route('reports.financial'));

        $this->actingAs($user)
            ->get(route('reports.financial'))
            ->assertOk()
            ->assertSee('Reporte financiero')
            ->assertSee(route('reports.financial'), false)
            ->assertDontSee(route('reports.clinical'), false)
            ->assertDontSee(route('reports.patients'), false)
            ->assertDontSee(route('reports.doctors'), false);
    }

    public function test_cashier_cannot_access_clinical_doctor_patient_or_general_reports(): void
    {
        $user = $this->userWithRole('caja_finanzas');

        $this->actingAs($user)->get(route('reports.clinical'))->assertForbidden();
        $this->actingAs($user)->get(route('reports.doctors'))->assertForbidden();
        $this->actingAs($user)->get(route('reports.patients'))->assertForbidden();
    }

    public function test_doctor_does_not_see_or_export_financial_reports(): void
    {
        $user = $this->userWithRole('medico');

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertRedirect(route('reports.appointments'));

        $this->actingAs($user)->get(route('reports.financial'))->assertForbidden();
        $this->actingAs($user)->get(route('reports.financial.export.pdf'))->assertForbidden();
    }

    public function test_doctor_reports_are_scoped_to_their_own_data(): void
    {
        $clinic = Clinic::factory()->create();
        $doctorUser = $this->userWithRole('medico', $clinic);
        $ownDoctor = Doctor::factory()->for($clinic)->create(['user_id' => $doctorUser->id]);
        $otherDoctor = $this->doctorForClinic($clinic, 'Doctor Ajeno Reporte');
        $ownAppointment = $this->appointmentForClinic($clinic, doctor: $ownDoctor, patientName: 'Paciente Cita Propia');
        $otherAppointment = $this->appointmentForClinic($clinic, doctor: $otherDoctor, patientName: 'Paciente Cita Ajena');
        $ownPatient = $this->patientForClinic($clinic, 'Paciente Clinico Propio');
        $otherPatient = $this->patientForClinic($clinic, 'Paciente Clinico Ajeno');
        Consultation::factory()->create([
            'patient_id' => $ownPatient->id,
            'doctor_id' => $ownDoctor->id,
            'consultation_date' => now(),
            'diagnosis' => 'Diagnostico propio visible',
        ]);
        Consultation::factory()->create([
            'patient_id' => $otherPatient->id,
            'doctor_id' => $otherDoctor->id,
            'consultation_date' => now(),
            'diagnosis' => 'Diagnostico ajeno oculto',
        ]);

        $this->actingAs($doctorUser)
            ->get(route('reports.appointments', [...$this->currentPeriod(), 'doctor_id' => $otherDoctor->id]))
            ->assertOk()
            ->assertSee($ownAppointment->patient->full_name)
            ->assertDontSee($otherAppointment->patient->full_name);

        $appointmentsXlsx = $this->actingAs($doctorUser)
            ->get(route('reports.appointments.export.xlsx', [...$this->currentPeriod(), 'doctor_id' => $otherDoctor->id]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Paciente Cita Propia', $appointmentsXlsx);
        $this->assertStringNotContainsString('Paciente Cita Ajena', $appointmentsXlsx);

        $clinicalXlsx = $this->actingAs($doctorUser)
            ->get(route('reports.clinical.export.xlsx', [...$this->currentPeriod(), 'doctor_id' => $otherDoctor->id]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Paciente Clinico Propio', $clinicalXlsx);
        $this->assertStringContainsString('Diagnostico propio visible', $clinicalXlsx);
        $this->assertStringNotContainsString('Paciente Clinico Ajeno', $clinicalXlsx);
        $this->assertStringNotContainsString('Diagnostico ajeno oculto', $clinicalXlsx);
    }

    public function test_receptionist_without_report_permission_cannot_access_financial_reports(): void
    {
        $user = $this->userWithRole('recepcionista');

        $this->actingAs($user)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($user)->get(route('reports.financial'))->assertForbidden();
        $this->actingAs($user)->get(route('reports.financial.export.csv'))->assertForbidden();
    }

    public function test_admin_and_cashier_can_export_financial_pdf(): void
    {
        $clinic = Clinic::factory()->create();
        $patient = $this->patientForClinic($clinic, 'Paciente PDF');
        $service = Service::factory()->for($clinic)->create();
        $this->paymentForClinic($clinic, $patient, $service, 'paid', 'cash', 75);

        foreach (['administrador', 'caja_finanzas'] as $role) {
            $this->actingAs($this->userWithRole($role, $clinic))
                ->get(route('reports.financial.export.pdf', $this->currentPeriod()))
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf');
        }
    }

    public function test_admin_and_cashier_can_export_financial_csv(): void
    {
        $clinic = Clinic::factory()->create();
        $patient = $this->patientForClinic($clinic, 'Paciente CSV');
        $service = Service::factory()->for($clinic)->create();
        $this->paymentForClinic($clinic, $patient, $service, 'paid', 'transfer', 45);

        foreach (['administrador', 'caja_finanzas'] as $role) {
            $response = $this->actingAs($this->userWithRole($role, $clinic))
                ->get(route('reports.financial.export.csv', $this->currentPeriod()))
                ->assertOk();

            $this->assertStringContainsString('Paciente CSV', $response->streamedContent());
        }
    }

    public function test_admin_and_cashier_can_export_financial_xlsx(): void
    {
        $clinic = Clinic::factory()->create([
            'name' => 'Clinica Excel Principal',
            'ruc' => '0999999999001',
            'address' => 'Av. Principal 123',
            'phone' => '0999999999',
        ]);
        $service = Service::factory()->for($clinic)->create(['name' => 'Ecografía Excel']);
        $paidPatient = $this->patientForClinic($clinic, 'Paciente Pagado Excel');
        $pendingPatient = $this->patientForClinic($clinic, 'Paciente Pendiente Excel');
        $cancelledPatient = $this->patientForClinic($clinic, 'Paciente Cancelado Excel');
        $refundedPatient = $this->patientForClinic($clinic, 'Paciente Reembolsado Excel');

        $this->paymentForClinic($clinic, $paidPatient, $service, 'paid', 'card', 120);
        $this->paymentForClinic($clinic, $pendingPatient, $service, 'pending', 'cash', 80);
        $this->paymentForClinic($clinic, $cancelledPatient, $service, 'cancelled', 'transfer', 60);
        $this->paymentForClinic($clinic, $refundedPatient, $service, 'refunded', 'card', 40);

        foreach (['administrador', 'caja_finanzas'] as $role) {
            $user = $this->userWithRole($role, $clinic);
            $response = $this->actingAs($user)
                ->get(route('reports.financial.export.xlsx', $this->currentPeriod()))
                ->assertOk();

            $xlsx = $response->streamedContent();
            $this->assertStringStartsWith('PK', $xlsx);
            $this->assertStringContainsString('reporte-financiero-clinica-excel-principal-', $response->headers->get('content-disposition'));
            $this->assertStringContainsString('.xlsx', $response->headers->get('content-disposition'));
            $this->assertStringContainsString('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('content-type'));
            $this->assertStringContainsString('Reporte financiero', $xlsx);
            $this->assertStringContainsString('Clínica: Clinica Excel Principal', $xlsx);
            $this->assertStringContainsString('RUC: 0999999999001', $xlsx);
            $this->assertStringContainsString('Dirección: Av. Principal 123', $xlsx);
            $this->assertStringContainsString('Teléfono: 0999999999', $xlsx);
            $this->assertStringContainsString('Rango: ', $xlsx);
            $this->assertStringContainsString('Generado por: '.$user->name, $xlsx);
            $this->assertStringContainsString('Generado el: ', $xlsx);
            $this->assertStringContainsString('Monto registrado', $xlsx);
            $this->assertStringContainsString('Cancelados/reembolsados', $xlsx);
            $this->assertStringContainsString('Número de pago', $xlsx);
            $this->assertStringContainsString('Paciente Pagado Excel', $xlsx);
            $this->assertStringContainsString('Paciente Pendiente Excel', $xlsx);
            $this->assertStringContainsString('Paciente Cancelado Excel', $xlsx);
            $this->assertStringContainsString('Paciente Reembolsado Excel', $xlsx);
            $this->assertStringContainsString('Ecografía Excel', $xlsx);
            $this->assertStringContainsString('Pagado', $xlsx);
            $this->assertStringContainsString('Pendiente', $xlsx);
            $this->assertStringContainsString('Cancelado', $xlsx);
            $this->assertStringContainsString('Reembolsado', $xlsx);
            $this->assertStringContainsString('FF047857', $xlsx);
            $this->assertStringContainsString('FFB45309', $xlsx);
            $this->assertStringContainsString('FFDC2626', $xlsx);
            $this->assertStringContainsString('FFFEF3C7', $xlsx);
            $this->assertStringContainsString('FFFEE2E2', $xlsx);
            $this->assertStringContainsString('wrapText="1"', $xlsx);
            $this->assertStringContainsString('pane ySplit="', $xlsx);
            $this->assertStringContainsString('autoFilter ref="A', $xlsx);
        }
    }

    public function test_financial_xlsx_uses_current_active_clinic_header_instead_of_legacy_user_clinic(): void
    {
        $legacyClinic = Clinic::factory()->create(['name' => 'Clinica Legacy']);
        $secondaryClinic = Clinic::factory()->create(['name' => 'Sucursal Norte Activa']);
        $legacyService = Service::factory()->for($legacyClinic)->create(['name' => 'Servicio Legacy']);
        $secondaryService = Service::factory()->for($secondaryClinic)->create(['name' => 'Servicio Sucursal']);
        $legacyPatient = $this->patientForClinic($legacyClinic, 'Paciente Legacy');
        $secondaryPatient = $this->patientForClinic($secondaryClinic, 'Paciente Sucursal');
        $this->paymentForClinic($legacyClinic, $legacyPatient, $legacyService, 'paid', 'cash', 30);
        $this->paymentForClinic($secondaryClinic, $secondaryPatient, $secondaryService, 'paid', 'cash', 40);

        $user = $this->userWithRole('caja_finanzas', $legacyClinic);
        $user->clinics()->syncWithoutDetaching([$secondaryClinic->id]);
        $user->forceFill(['current_clinic_id' => $secondaryClinic->id])->save();

        $response = $this->actingAs($user)
            ->get(route('reports.financial.export.xlsx', $this->currentPeriod()))
            ->assertOk();

        $xlsx = $response->streamedContent();

        $this->assertStringContainsString('Clínica: Sucursal Norte Activa', $xlsx);
        $this->assertStringContainsString('Paciente Sucursal', $xlsx);
        $this->assertStringContainsString('Servicio Sucursal', $xlsx);
        $this->assertStringNotContainsString('Clínica: Clinica Legacy', $xlsx);
        $this->assertStringNotContainsString('Paciente Legacy', $xlsx);
        $this->assertStringNotContainsString('Servicio Legacy', $xlsx);
        $this->assertStringContainsString('reporte-financiero-sucursal-norte-activa-', $response->headers->get('content-disposition'));
    }

    public function test_financial_xlsx_changes_header_when_user_changes_active_clinic(): void
    {
        $firstClinic = Clinic::factory()->create(['name' => 'Clinica Centro']);
        $secondClinic = Clinic::factory()->create(['name' => 'Clinica Sur']);
        $user = $this->userWithRole('caja_finanzas', $firstClinic);
        $user->clinics()->syncWithoutDetaching([$secondClinic->id]);

        $firstResponse = $this->actingAs($user)
            ->get(route('reports.financial.export.xlsx', $this->currentPeriod()))
            ->assertOk()
            ->streamedContent();

        $user->forceFill(['current_clinic_id' => $secondClinic->id])->save();

        $secondResponse = $this->actingAs($user->fresh())
            ->get(route('reports.financial.export.xlsx', $this->currentPeriod()))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Clínica: Clinica Centro', $firstResponse);
        $this->assertStringNotContainsString('Clínica: Clinica Sur', $firstResponse);
        $this->assertStringContainsString('Clínica: Clinica Sur', $secondResponse);
        $this->assertStringNotContainsString('Clínica: Clinica Centro', $secondResponse);
    }

    public function test_doctor_and_receptionist_cannot_export_financial_xlsx(): void
    {
        $this->actingAs($this->userWithRole('medico'))
            ->get(route('reports.financial.export.xlsx'))
            ->assertForbidden();

        $this->actingAs($this->userWithRole('recepcionista'))
            ->get(route('reports.financial.export.xlsx'))
            ->assertForbidden();
    }

    public function test_financial_xlsx_respects_clinic_and_filters(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $doctor = $this->doctorForClinic($clinic, 'Doctora Excel Visible');
        $otherDoctor = $this->doctorForClinic($clinic, 'Doctor Excel Oculto');
        $service = Service::factory()->for($clinic)->create(['name' => 'Servicio Excel Visible']);
        $otherService = Service::factory()->for($clinic)->create(['name' => 'Servicio Excel Oculto']);
        $foreignService = Service::factory()->for($otherClinic)->create(['name' => 'Servicio Otra Clinica']);

        $visiblePatient = $this->patientForClinic($clinic, 'Paciente Excel Visible');
        $appointment = $this->appointmentForClinic($clinic, $visiblePatient, $doctor, date: '2026-06-10');
        $appointment->update(['service_id' => $service->id]);
        Payment::factory()->forAppointment($appointment)->create([
            'amount' => 120,
            'payment_status' => 'paid',
            'payment_method' => 'card',
            'payment_date' => '2026-06-10 10:00:00',
        ]);

        $hiddenByStatus = $this->patientForClinic($clinic, 'Paciente Estado Oculto');
        $this->paymentForClinic($clinic, $hiddenByStatus, $service, 'pending', 'card', 90, paymentDate: null);

        $hiddenByMethod = $this->patientForClinic($clinic, 'Paciente Metodo Oculto');
        $this->paymentForClinic($clinic, $hiddenByMethod, $service, 'paid', 'cash', 90, paymentDate: '2026-06-10 11:00:00');

        $hiddenByServicePatient = $this->patientForClinic($clinic, 'Paciente Servicio Oculto');
        $this->paymentForClinic($clinic, $hiddenByServicePatient, $otherService, 'paid', 'card', 90, paymentDate: '2026-06-10 12:00:00');

        $hiddenByDoctorPatient = $this->patientForClinic($clinic, 'Paciente Doctor Oculto');
        $hiddenAppointment = $this->appointmentForClinic($clinic, $hiddenByDoctorPatient, $otherDoctor, date: '2026-06-10');
        $hiddenAppointment->update(['service_id' => $service->id]);
        Payment::factory()->forAppointment($hiddenAppointment)->create([
            'amount' => 90,
            'payment_status' => 'paid',
            'payment_method' => 'card',
            'payment_date' => '2026-06-10 13:00:00',
        ]);

        $foreignPatient = $this->patientForClinic($otherClinic, 'Paciente Otra Clinica');
        $this->paymentForClinic($otherClinic, $foreignPatient, $foreignService, 'paid', 'card', 90, paymentDate: '2026-06-10 14:00:00');

        $xlsx = $this->actingAs($this->userWithRole('caja_finanzas', $clinic))
            ->get(route('reports.financial.export.xlsx', [
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-30',
                'payment_status' => 'paid',
                'payment_method' => 'card',
                'service_id' => $service->id,
                'doctor_id' => $doctor->id,
            ]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Paciente Excel Visible', $xlsx);
        $this->assertStringContainsString('Servicio Excel Visible', $xlsx);
        $this->assertStringContainsString('Doctora Excel Visible', $xlsx);
        $this->assertStringNotContainsString('Paciente Estado Oculto', $xlsx);
        $this->assertStringNotContainsString('Paciente Metodo Oculto', $xlsx);
        $this->assertStringNotContainsString('Paciente Servicio Oculto', $xlsx);
        $this->assertStringNotContainsString('Paciente Doctor Oculto', $xlsx);
        $this->assertStringNotContainsString('Paciente Otra Clinica', $xlsx);
    }

    public function test_financial_xlsx_registers_audit_log(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userWithRole('caja_finanzas', $clinic);
        $patient = $this->patientForClinic($clinic, 'Paciente Auditoria Excel');
        $service = Service::factory()->for($clinic)->create();
        $this->paymentForClinic($clinic, $patient, $service, 'paid', 'transfer', 65);

        $this->actingAs($user)
            ->get(route('reports.financial.export.xlsx', $this->currentPeriod()))
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'action' => 'report.financial_exported_xlsx',
            'module' => 'reports',
        ]);

        $log = AuditLog::where('action', 'report.financial_exported_xlsx')->firstOrFail();
        $this->assertSame('xlsx', $log->new_values['format']);
        $this->assertSame(1, $log->new_values['total_records']);
    }
    public function test_financial_csv_export_respects_date_range(): void
    {
        $clinic = Clinic::factory()->create();
        $service = Service::factory()->for($clinic)->create();
        $inside = $this->patientForClinic($clinic, 'Paciente Dentro CSV');
        $outside = $this->patientForClinic($clinic, 'Paciente Fuera CSV');
        $this->paymentForClinic($clinic, $inside, $service, 'paid', 'cash', 20, 'Dentro', '2026-06-10 10:00:00');
        $this->paymentForClinic($clinic, $outside, $service, 'paid', 'cash', 30, 'Fuera', '2026-05-10 10:00:00');

        $csv = $this->actingAs($this->userWithRole('administrador', $clinic))
            ->get(route('reports.financial.export.csv', ['start_date' => '2026-06-01', 'end_date' => '2026-06-30']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Paciente Dentro CSV', $csv);
        $this->assertStringNotContainsString('Paciente Fuera CSV', $csv);
    }

    public function test_financial_csv_export_respects_payment_status(): void
    {
        $clinic = Clinic::factory()->create();
        $service = Service::factory()->for($clinic)->create();
        $paid = $this->patientForClinic($clinic, 'Paciente Pagado CSV');
        $pending = $this->patientForClinic($clinic, 'Paciente Pendiente CSV');
        $this->paymentForClinic($clinic, $paid, $service, 'paid', 'cash', 20);
        $this->paymentForClinic($clinic, $pending, $service, 'pending', 'cash', 30);

        $csv = $this->actingAs($this->userWithRole('caja_finanzas', $clinic))
            ->get(route('reports.financial.export.csv', [...$this->currentPeriod(), 'payment_status' => 'paid']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Paciente Pagado CSV', $csv);
        $this->assertStringNotContainsString('Paciente Pendiente CSV', $csv);
    }

    public function test_financial_csv_export_respects_clinic_scope(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $service = Service::factory()->for($clinic)->create();
        $otherService = Service::factory()->for($otherClinic)->create();
        $own = $this->patientForClinic($clinic, 'Paciente Propio CSV');
        $other = $this->patientForClinic($otherClinic, 'Paciente Ajeno CSV');
        $this->paymentForClinic($clinic, $own, $service, 'paid', 'cash', 20);
        $this->paymentForClinic($otherClinic, $other, $otherService, 'paid', 'cash', 30);

        $csv = $this->actingAs($this->userWithRole('administrador', $clinic))
            ->get(route('reports.financial.export.csv', $this->currentPeriod()))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Paciente Propio CSV', $csv);
        $this->assertStringNotContainsString('Paciente Ajeno CSV', $csv);
    }

    public function test_financial_pdf_csv_and_print_register_audit_logs(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userWithRole('administrador', $clinic);
        $patient = $this->patientForClinic($clinic, 'Paciente Auditoria');
        $service = Service::factory()->for($clinic)->create();
        $this->paymentForClinic($clinic, $patient, $service, 'paid', 'card', 88);

        $this->actingAs($user)->get(route('reports.financial.export.pdf', $this->currentPeriod()))->assertOk();
        $this->actingAs($user)->get(route('reports.financial.export.csv', $this->currentPeriod()))->assertOk();
        $this->actingAs($user)->get(route('reports.financial.print', $this->currentPeriod()))->assertOk();

        foreach (['report.financial_exported_pdf', 'report.financial_exported_csv', 'report.financial_printed'] as $action) {
            $this->assertDatabaseHas('audit_logs', [
                'clinic_id' => $clinic->id,
                'user_id' => $user->id,
                'action' => $action,
                'module' => 'reports',
            ]);
        }

        $log = AuditLog::where('action', 'report.financial_exported_csv')->firstOrFail();
        $this->assertSame('csv', $log->new_values['format']);
        $this->assertSame(1, $log->new_values['total_records']);
    }

    public function test_financial_report_uses_guayaquil_timezone_for_boundary_dates(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-07-20 23:45:00', 'America/Guayaquil'));
        $clinic = Clinic::factory()->create();
        $patient = $this->patientForClinic($clinic, 'Paciente Hora Local');
        $service = Service::factory()->for($clinic)->create();
        $this->paymentForClinic($clinic, $patient, $service, 'paid', 'cash', 50, 'Pago local', '2026-07-20 23:30:00');

        $this->actingAs($this->userWithRole('caja_finanzas', $clinic))
            ->get(route('reports.financial', ['start_date' => '2026-07-20', 'end_date' => '2026-07-20']))
            ->assertOk()
            ->assertSee('20/07/2026 23:30')
            ->assertDontSee('21/07/2026');
    }
    public function test_financial_print_view_is_landscape_and_uses_fixed_pdf_table(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->userWithRole('caja_finanzas', $clinic);
        $patient = $this->patientForClinic($clinic, 'Paciente PDF Landscape');
        $service = Service::factory()->for($clinic)->create();
        $this->paymentForClinic($clinic, $patient, $service, 'paid', 'cash', 75);

        $this->actingAs($user)
            ->get(route('reports.financial.print', $this->currentPeriod()))
            ->assertOk()
            ->assertSee('@page { size: A4 landscape;', false)
            ->assertSee('table-layout: fixed', false)
            ->assertSee('payments-table', false);
    }

    public function test_financial_csv_has_utf8_bom_semicolon_delimiter_and_accents(): void
    {
        $clinic = Clinic::factory()->create();
        $patient = $this->patientForClinic($clinic, 'José Ñúñez');
        $service = Service::factory()->for($clinic)->create(['name' => 'Ecografía médica']);
        $this->paymentForClinic($clinic, $patient, $service, 'paid', 'transfer', 45);

        $csv = $this->actingAs($this->userWithRole('caja_finanzas', $clinic))
            ->get(route('reports.financial.export.csv', $this->currentPeriod()))
            ->assertOk()
            ->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $lines = preg_split('/\r\n|\r|\n/', substr($csv, 3));
        $this->assertStringContainsString(';', $lines[0]);
        $this->assertSame([
            'Número de pago',
            'Fecha de pago',
            'Paciente',
            'Identificación',
            'Servicio',
            'Médico',
            'Método de pago',
            'Estado',
            'Monto',
        ], str_getcsv($lines[0], ';'));
        $this->assertStringContainsString('José', $csv);
        $this->assertStringContainsString('Ñúñez', $csv);
        $this->assertStringContainsString('Ecografía médica', $csv);
    }

    public function test_doctor_cannot_export_financial_csv(): void
    {
        $this->actingAs($this->userWithRole('medico'))
            ->get(route('reports.financial.export.csv'))
            ->assertForbidden();
    }

    public function test_cashier_can_view_financial_audit_but_not_global_audit(): void
    {
        $clinic = Clinic::factory()->create();
        $cashier = $this->userWithRole('caja_finanzas', $clinic);
        AuditLog::create([
            'clinic_id' => $clinic->id,
            'user_id' => $cashier->id,
            'action' => 'payment.paid',
            'module' => 'payments',
            'description' => 'Pago registrado por caja.',
            'new_values' => ['payment_id' => 123],
        ]);

        $this->actingAs($cashier)
            ->get(route('financial-audit.index'))
            ->assertOk()
            ->assertSee('Registro de caja')
            ->assertSee('Pago registrado por caja.');

        $this->actingAs($cashier)
            ->get(route('audit-logs.index'))
            ->assertForbidden();
    }

    public function test_doctor_cannot_view_financial_audit(): void
    {
        $this->actingAs($this->userWithRole('medico'))
            ->get(route('financial-audit.index'))
            ->assertForbidden();
    }

    public function test_financial_audit_respects_clinic_scope(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $cashier = $this->userWithRole('caja_finanzas', $clinic);
        AuditLog::create([
            'clinic_id' => $clinic->id,
            'user_id' => $cashier->id,
            'action' => 'payment.receipt_printed',
            'module' => 'payments',
            'description' => 'Recibo visible.',
            'new_values' => ['payment_id' => 1],
        ]);
        AuditLog::create([
            'clinic_id' => $otherClinic->id,
            'user_id' => null,
            'action' => 'payment.receipt_printed',
            'module' => 'payments',
            'description' => 'Recibo oculto.',
            'new_values' => ['payment_id' => 2],
        ]);

        $this->actingAs($cashier)
            ->get(route('financial-audit.index'))
            ->assertOk()
            ->assertSee('Recibo visible.')
            ->assertDontSee('Recibo oculto.');
    }

    public function test_financial_audit_does_not_show_non_financial_events(): void
    {
        $clinic = Clinic::factory()->create();
        $cashier = $this->userWithRole('caja_finanzas', $clinic);
        AuditLog::create([
            'clinic_id' => $clinic->id,
            'user_id' => $cashier->id,
            'action' => 'consultation.created',
            'module' => 'consultations',
            'description' => 'Evento clinico oculto.',
            'new_values' => [],
        ]);

        $this->actingAs($cashier)
            ->get(route('financial-audit.index'))
            ->assertOk()
            ->assertDontSee('Evento clinico oculto.');
    }

    public function test_cashier_dashboard_has_real_financial_links_and_no_obsolete_phase_text(): void
    {
        $cashier = $this->userWithRole('caja_finanzas');

        $this->actingAs($cashier)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Registro de caja')
            ->assertSee(route('financial-audit.index'), false)
            ->assertSee(route('reports.financial'), false)
            ->assertSee(route('payments.index', ['payment_status' => 'pending']), false)
            ->assertDontSee('siguiente fase')
            ->assertDontSee('proxima fase')
            ->assertDontSee(route('audit-logs.index'), false);

        $this->actingAs($cashier)->get(route('financial-audit.index'))->assertOk();
        $this->actingAs($cashier)->get(route('reports.financial'))->assertOk();
        $this->actingAs($cashier)->get(route('payments.index'))->assertOk();
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
        return ['reports.index', 'reports.appointments', 'reports.appointments.export.pdf', 'reports.appointments.export.csv', 'reports.appointments.export.xlsx', 'reports.appointments.print', 'reports.clinical', 'reports.clinical.export.pdf', 'reports.clinical.export.csv', 'reports.clinical.export.xlsx', 'reports.clinical.print', 'reports.financial', 'reports.financial.export.pdf', 'reports.financial.export.csv', 'reports.financial.export.xlsx', 'reports.financial.print', 'financial-audit.index', 'reports.patients', 'reports.doctors', 'reports.services'];
    }

    private function userForClinic(Clinic $clinic): User
    {
        return User::factory()->create(['clinic_id' => $clinic->id]);
    }

    private function userWithRole(string $role, ?Clinic $clinic = null): User
    {
        $user = $this->userForClinic($clinic ?? Clinic::factory()->create());
        $user->syncRoles([$role]);

        return $user;
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

    private function paymentForClinic(Clinic $clinic, Patient $patient, Service $service, string $status, string $method, float $amount, string $notes = 'Pago de prueba', ?string $paymentDate = null): Payment
    {
        return Payment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'service_id' => $service->id,
            'amount' => $amount,
            'payment_status' => $status,
            'payment_method' => $method,
            'payment_date' => $status === 'pending' ? null : ($paymentDate ?? now()),
            'notes' => $notes,
        ]);
    }

    /** @return array{start_date: string, end_date: string} */
    private function currentPeriod(): array
    {
        return ['start_date' => now()->startOfMonth()->toDateString(), 'end_date' => now()->endOfMonth()->toDateString()];
    }
}
