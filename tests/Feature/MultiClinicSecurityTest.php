<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class MultiClinicSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_switch_to_foreign_clinic(): void
    {
        $user = $this->adminForClinic($clinic = Clinic::factory()->create());
        $foreignClinic = Clinic::factory()->create();

        $this->actingAs($user)
            ->post(route('switch-clinic', $foreignClinic))
            ->assertForbidden();

        $this->assertSame($clinic->id, $user->refresh()->current_clinic_id);
    }

    public function test_user_with_two_clinics_can_switch_between_them_and_audit_is_created(): void
    {
        $user = $this->adminForClinic($firstClinic = Clinic::factory()->create());
        $secondClinic = Clinic::factory()->create();
        $user->clinics()->attach($secondClinic->id);

        $this->actingAs($user)
            ->post(route('switch-clinic', $secondClinic))
            ->assertRedirect(route('dashboard'));

        $this->assertSame($secondClinic->id, $user->refresh()->current_clinic_id);
        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $secondClinic->id,
            'user_id' => $user->id,
            'action' => 'clinic.switched',
            'module' => 'clinics',
        ]);

        $log = AuditLog::where('action', 'clinic.switched')->firstOrFail();
        $this->assertSame($firstClinic->id, $log->new_values['previous_clinic_id']);
        $this->assertSame($secondClinic->id, $log->new_values['new_clinic_id']);
    }

    public function test_invalid_current_clinic_is_cleared_and_safe_fallback_is_used(): void
    {
        $user = $this->adminForClinic($clinic = Clinic::factory()->create());
        $foreignClinic = Clinic::factory()->create();
        $user->forceFill(['current_clinic_id' => $foreignClinic->id])->save();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();

        $this->assertSame($clinic->id, $user->refresh()->current_clinic_id);
    }

    public function test_foreign_current_clinic_does_not_leak_operational_data(): void
    {
        $user = $this->adminForClinic($clinic = Clinic::factory()->create());
        $foreignClinic = Clinic::factory()->create();
        $visiblePatient = Patient::factory()->for($clinic)->create(['first_name' => 'Visible', 'last_name' => 'Paciente']);
        $hiddenPatient = Patient::factory()->for($foreignClinic)->create(['first_name' => 'Oculto', 'last_name' => 'Paciente']);
        $user->forceFill(['current_clinic_id' => $foreignClinic->id])->save();

        $this->actingAs($user)
            ->get(route('patients.index'))
            ->assertOk()
            ->assertSee($visiblePatient->full_name)
            ->assertDontSee($hiddenPatient->full_name);
    }

    public function test_inactive_clinic_blocks_dashboard_and_operational_modules(): void
    {
        $clinic = Clinic::factory()->create(['status' => 'inactive']);
        $user = $this->adminForClinic($clinic);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertForbidden()
            ->assertSee('La clinica seleccionada esta inactiva. Contacte al administrador.');

        $this->actingAs($user)
            ->get(route('patients.index'))
            ->assertForbidden();
    }

    public function test_inactive_clinic_cannot_be_selected(): void
    {
        $user = $this->adminForClinic(Clinic::factory()->create());
        $inactiveClinic = Clinic::factory()->create(['status' => 'inactive']);
        $user->clinics()->attach($inactiveClinic->id);

        $this->actingAs($user)
            ->post(route('switch-clinic', $inactiveClinic))
            ->assertForbidden();
    }

    public function test_super_admin_can_see_inactive_clinics_and_admin_cannot_enter_super_admin(): void
    {
        $inactiveClinic = Clinic::factory()->create(['name' => 'Clinica Inactiva SaaS', 'status' => 'inactive']);
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');
        $admin = $this->adminForClinic(Clinic::factory()->create());

        $this->actingAs($superAdmin)
            ->get(route('super-admin.clinics.index'))
            ->assertOk()
            ->assertSee($inactiveClinic->name);

        $this->actingAs($admin)
            ->get(route('super-admin.clinics.index'))
            ->assertForbidden();
    }

    public function test_audit_logger_uses_active_clinic_instead_of_legacy_user_clinic(): void
    {
        $primaryClinic = Clinic::factory()->create();
        $activeClinic = Clinic::factory()->create();
        $user = $this->adminForClinic($primaryClinic);
        $user->clinics()->attach($activeClinic->id);
        $user->forceFill(['current_clinic_id' => $activeClinic->id])->save();

        $this->actingAs($user);
        AuditLogger::log('audit.test', 'tests', null, [], ['note' => 'active clinic test']);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $activeClinic->id,
            'user_id' => $user->id,
            'action' => 'audit.test',
        ]);
    }

    public function test_report_export_from_secondary_clinic_registers_active_clinic_in_audit(): void
    {
        $primaryClinic = Clinic::factory()->create();
        $activeClinic = Clinic::factory()->create();
        $user = $this->adminForClinic($primaryClinic);
        $user->clinics()->attach($activeClinic->id);
        $user->forceFill(['current_clinic_id' => $activeClinic->id])->save();
        $patient = Patient::factory()->for($activeClinic)->create();
        $service = Service::factory()->for($activeClinic)->create();
        Payment::factory()->create([
            'clinic_id' => $activeClinic->id,
            'patient_id' => $patient->id,
            'service_id' => $service->id,
            'payment_status' => 'paid',
            'payment_date' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('reports.financial.export.csv'))
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $activeClinic->id,
            'user_id' => $user->id,
            'action' => 'report.financial_exported_csv',
        ]);
    }

    public function test_admin_multi_clinic_sees_users_by_pivot_in_active_clinic(): void
    {
        $primaryClinic = Clinic::factory()->create();
        $activeClinic = Clinic::factory()->create();
        $admin = $this->adminForClinic($primaryClinic);
        $admin->clinics()->attach($activeClinic->id);
        $admin->forceFill(['current_clinic_id' => $activeClinic->id])->save();
        $visibleUser = User::factory()->create(['clinic_id' => $primaryClinic->id, 'name' => 'Usuario Pivot Visible']);
        $visibleUser->clinics()->attach($activeClinic->id);
        $hiddenUser = User::factory()->create(['clinic_id' => $primaryClinic->id, 'name' => 'Usuario Pivot Oculto']);

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee($visibleUser->name)
            ->assertDontSee($hiddenUser->name);
    }

    public function test_admin_cannot_assign_user_to_foreign_clinic(): void
    {
        $admin = $this->adminForClinic(Clinic::factory()->create());
        $foreignClinic = Clinic::factory()->create();

        $this->actingAs($admin)
            ->from(route('users.create'))
            ->post(route('users.store'), [
                ...$this->validUserPayload(),
                'email' => 'foreign-clinic@mediflow.test',
                'clinic_ids' => [$foreignClinic->id],
            ])
            ->assertRedirect(route('users.create'))
            ->assertSessionHasErrors('clinic_ids');

        $this->assertDatabaseMissing('users', ['email' => 'foreign-clinic@mediflow.test']);
    }

    public function test_patients_appointments_and_payments_remain_scoped_to_active_clinic(): void
    {
        $primaryClinic = Clinic::factory()->create();
        $activeClinic = Clinic::factory()->create();
        $user = $this->adminForClinic($primaryClinic);
        $user->clinics()->attach($activeClinic->id);
        $user->forceFill(['current_clinic_id' => $activeClinic->id])->save();

        $visiblePatient = Patient::factory()->for($activeClinic)->create(['first_name' => 'Visible', 'last_name' => 'Scope']);
        $hiddenPatient = Patient::factory()->for($primaryClinic)->create(['first_name' => 'Hidden', 'last_name' => 'Scope']);
        $visibleAppointment = Appointment::factory()->create([
            'clinic_id' => $activeClinic->id,
            'patient_id' => $visiblePatient->id,
        ]);
        Appointment::factory()->create([
            'clinic_id' => $primaryClinic->id,
            'patient_id' => $hiddenPatient->id,
        ]);
        Payment::factory()->forAppointment($visibleAppointment)->create(['payment_status' => 'pending']);
        Payment::factory()->create([
            'clinic_id' => $primaryClinic->id,
            'patient_id' => $hiddenPatient->id,
            'payment_status' => 'pending',
        ]);

        $this->actingAs($user)
            ->get(route('patients.index'))
            ->assertOk()
            ->assertSee($visiblePatient->full_name)
            ->assertDontSee($hiddenPatient->full_name);

        $this->actingAs($user)
            ->get(route('appointments.index'))
            ->assertOk()
            ->assertSee($visiblePatient->full_name)
            ->assertDontSee($hiddenPatient->full_name);

        $this->actingAs($user)
            ->get(route('payments.index'))
            ->assertOk()
            ->assertSee($visiblePatient->full_name)
            ->assertDontSee($hiddenPatient->full_name);
    }

    public function test_super_admin_onboarding_rolls_back_if_admin_creation_fails(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        User::creating(function (User $user): void {
            if ($user->email === 'fail-admin@mediflow.test') {
                throw new RuntimeException('Forced admin creation failure.');
            }
        });

        $this->actingAs($superAdmin)
            ->post(route('super-admin.clinics.store'), [
                'name' => 'Clinica Rollback',
                'email' => 'rollback@mediflow.test',
                'status' => 'active',
                'admin_name' => 'Admin Rollback',
                'admin_email' => 'fail-admin@mediflow.test',
                'admin_password' => 'Password123',
                'admin_password_confirmation' => 'Password123',
            ])
            ->assertStatus(500);

        $this->assertDatabaseMissing('clinics', ['email' => 'rollback@mediflow.test']);
    }

    private function adminForClinic(Clinic $clinic): User
    {
        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
            'current_clinic_id' => $clinic->id,
        ]);
        $user->assignRole('administrador');

        return $user;
    }

    /** @return array<string, mixed> */
    private function validUserPayload(): array
    {
        return [
            'name' => 'Usuario Nuevo',
            'email' => 'usuario-nuevo@mediflow.test',
            'phone' => '0991234567',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'role' => 'recepcionista',
            'status' => 'active',
            'specialty_id' => null,
            'license_number' => null,
            'doctor_phone' => null,
            'consultation_fee' => '0.00',
            'doctor_status' => 'active',
        ];
    }
}
