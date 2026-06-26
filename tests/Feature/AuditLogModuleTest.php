<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Service;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AuditLogModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_audit_logs(): void
    {
        $admin = $this->userWithRole('administrador');

        $this->actingAs($admin)
            ->get(route('audit-logs.index'))
            ->assertOk()
            ->assertSee('Auditor');
    }

    public function test_user_without_audit_permission_receives_403(): void
    {
        $user = $this->userWithRole('medico');

        $this->actingAs($user)
            ->get(route('audit-logs.index'))
            ->assertForbidden();
    }

    public function test_non_admin_roles_do_not_see_audit_sidebar_link(): void
    {
        foreach (['medico', 'caja_finanzas', 'recepcionista'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertDontSee('Auditor', false);
        }
    }

    public function test_creating_patient_generates_audit_log(): void
    {
        $admin = $this->userWithRole('administrador');

        $this->actingAs($admin)->post(route('patients.store'), $this->patientPayload())
            ->assertRedirect(route('patients.index'));

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $admin->clinic_id,
            'user_id' => $admin->id,
            'action' => 'patient.created',
            'module' => 'patients',
        ]);
    }

    public function test_creating_appointment_generates_appointment_and_pending_payment_audit_logs(): void
    {
        [$admin, $patient, $doctor, $service] = $this->clinicalSetup();

        $this->actingAs($admin)->post(route('appointments.store'), $this->appointmentPayload($patient, $doctor, $service))
            ->assertRedirect(route('appointments.index'));

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $admin->clinic_id,
            'action' => 'appointment.created',
            'module' => 'appointments',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $admin->clinic_id,
            'action' => 'payment.pending_created',
            'module' => 'payments',
        ]);
    }

    public function test_collecting_payment_generates_paid_audit_log(): void
    {
        [$admin, $patient, $doctor, $service] = $this->clinicalSetup();
        $appointment = Appointment::factory()->create([
            'clinic_id' => $admin->clinic_id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'service_id' => $service->id,
            'status' => 'scheduled',
        ]);
        $payment = Payment::factory()->forAppointment($appointment)->create([
            'payment_status' => 'pending',
            'payment_method' => 'cash',
            'amount' => 25,
        ]);

        $this->actingAs($admin)->put(route('payments.update', $payment), [
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'amount' => 25,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'payment_date' => now()->format('Y-m-d H:i:s'),
            'notes' => 'Pago de consulta',
        ])->assertRedirect(route('payments.show', $payment));

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $admin->clinic_id,
            'action' => 'payment.paid',
            'module' => 'payments',
        ]);
    }

    public function test_signing_prescription_generates_audit_log(): void
    {
        [$admin, $patient, $doctor] = $this->clinicalSetup(withService: false);
        $prescription = $this->prescriptionFor($patient, $doctor);

        $this->actingAs($admin)->post(route('prescriptions.sign', $prescription))
            ->assertRedirect(route('prescriptions.show', $prescription));

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $admin->clinic_id,
            'action' => 'prescription.signed',
            'module' => 'prescriptions',
        ]);
    }

    public function test_sending_prescription_email_generates_audit_log(): void
    {
        Mail::fake();
        [$admin, $patient, $doctor] = $this->clinicalSetup(withService: false);
        $prescription = $this->prescriptionFor($patient, $doctor);

        $this->actingAs($admin)->post(route('prescriptions.send-email', $prescription), [
            'email' => 'paciente@example.com',
        ])->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $admin->clinic_id,
            'action' => 'prescription.emailed',
            'module' => 'prescriptions',
        ]);
    }

    public function test_filters_by_module_action_user_and_date(): void
    {
        $admin = $this->userWithRole('administrador');
        $otherUser = User::factory()->create(['clinic_id' => $admin->clinic_id]);
        $otherUser->assignRole('recepcionista');

        $visible = AuditLog::create([
            'clinic_id' => $admin->clinic_id,
            'user_id' => $admin->id,
            'action' => 'payment.paid',
            'module' => 'payments',
            'description' => 'Pago visible auditado',
            'ip_address' => '127.0.0.1',
        ]);
        $visible->forceFill(['created_at' => now()])->save();

        $hidden = AuditLog::create([
            'clinic_id' => $admin->clinic_id,
            'user_id' => $otherUser->id,
            'action' => 'patient.created',
            'module' => 'patients',
            'description' => 'Paciente oculto por filtro',
        ]);
        $hidden->forceFill(['created_at' => now()->subDays(3)])->save();

        $this->actingAs($admin)->get(route('audit-logs.index', [
            'module' => 'payments',
            'action' => 'payment.paid',
            'user_id' => $admin->id,
            'date_from' => today()->format('Y-m-d'),
            'date_to' => today()->format('Y-m-d'),
            'search' => 'visible',
        ]))->assertOk()
            ->assertSee('Pago visible auditado')
            ->assertDontSee('Paciente oculto por filtro');
    }

    public function test_audit_logs_are_filtered_by_clinic(): void
    {
        $admin = $this->userWithRole('administrador');
        $otherClinic = Clinic::factory()->create();

        AuditLog::create([
            'clinic_id' => $admin->clinic_id,
            'user_id' => $admin->id,
            'action' => 'patient.created',
            'module' => 'patients',
            'description' => 'Log de mi clinica',
        ]);
        AuditLog::create([
            'clinic_id' => $otherClinic->id,
            'action' => 'payment.paid',
            'module' => 'payments',
            'description' => 'Log de otra clinica',
        ]);

        $this->actingAs($admin)->get(route('audit-logs.index'))
            ->assertOk()
            ->assertSee('Log de mi clinica')
            ->assertDontSee('Log de otra clinica');
    }

    public function test_sensitive_password_fields_are_not_stored_in_audit_values(): void
    {
        $admin = $this->userWithRole('administrador');
        $secret = 'SuperSecret123';

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Usuario Auditado',
            'email' => 'auditado@example.com',
            'phone' => '0999999999',
            'password' => $secret,
            'password_confirmation' => $secret,
            'role' => 'recepcionista',
            'status' => 'active',
        ])->assertRedirect(route('users.index'));

        $log = AuditLog::where('action', 'user.created')->latest('id')->firstOrFail();
        $payload = json_encode([$log->old_values, $log->new_values]);

        $this->assertStringNotContainsString('password', $payload);
        $this->assertStringNotContainsString($secret, $payload);
    }

    private function userWithRole(string $role, ?Clinic $clinic = null): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create([
            'clinic_id' => ($clinic ?? Clinic::factory()->create())->id,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function clinicalSetup(bool $withService = true): array
    {
        $admin = $this->userWithRole('administrador');
        $clinic = $admin->clinic;
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);
        $doctorUser = User::factory()->create(['clinic_id' => $clinic->id]);
        $doctorUser->assignRole('medico');
        $doctor = Doctor::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctorUser->id,
            'specialty_id' => Specialty::factory()->create()->id,
            'consultation_fee' => 25,
        ]);

        if (! $withService) {
            return [$admin, $patient, $doctor];
        }

        $service = Service::factory()->create([
            'clinic_id' => $clinic->id,
            'price' => 25,
            'duration_minutes' => 30,
            'status' => 'active',
        ]);

        return [$admin, $patient, $doctor, $service];
    }

    private function patientPayload(): array
    {
        return [
            'first_name' => 'Ana',
            'last_name' => 'Prueba',
            'identification_number' => '0912345678',
            'birth_date' => '1990-01-01',
            'gender' => 'femenino',
            'phone' => '0999999999',
            'email' => 'ana@example.com',
            'address' => 'Guayaquil',
            'blood_type' => 'O+',
            'allergies' => 'Sin alergias registradas',
            'emergency_contact_name' => 'Contacto',
            'emergency_contact_phone' => '0988888888',
            'status' => 'active',
        ];
    }

    private function appointmentPayload(Patient $patient, Doctor $doctor, Service $service): array
    {
        return [
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'service_id' => $service->id,
            'appointment_date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '',
            'reason' => 'Control general',
            'status' => 'scheduled',
            'notes' => 'Primera visita',
        ];
    }

    private function prescriptionFor(Patient $patient, Doctor $doctor): Prescription
    {
        $prescription = Prescription::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'consultation_id' => null,
            'prescription_date' => today()->format('Y-m-d'),
            'status' => 'active',
        ]);

        PrescriptionItem::factory()->create([
            'prescription_id' => $prescription->id,
            'medication_name' => 'Paracetamol',
        ]);

        return $prescription;
    }
}
