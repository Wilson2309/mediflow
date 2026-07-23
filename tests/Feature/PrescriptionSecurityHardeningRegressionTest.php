<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PrescriptionSecurityHardeningRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_bound_routes_hide_foreign_resource_from_actor_without_prescription_permissions(): void
    {
        $sourceClinic = $this->clinic();
        $targetClinic = $this->clinic();
        $actor = $this->userForClinic($sourceClinic, 'recepcionista');
        $targetOwner = $this->userForClinic($targetClinic, 'medico');
        $foreignPrescription = $this->prescriptionFor($targetClinic, $targetOwner);
        $missingId = (int) Prescription::max('id') + 1000;

        $foreignWeb = $this->actingAs($actor)
            ->get(route('prescriptions.show', $foreignPrescription));
        $missingWeb = $this->actingAs($actor)
            ->get(route('prescriptions.show', $missingId));

        $foreignWeb->assertNotFound();
        $missingWeb->assertNotFound();
        $this->assertSame($foreignWeb->getContent(), $missingWeb->getContent());

        $requests = [
            ['GET', route('prescriptions.show', $foreignPrescription), route('prescriptions.show', $missingId), []],
            ['GET', route('prescriptions.edit', $foreignPrescription), route('prescriptions.edit', $missingId), []],
            ['GET', route('prescriptions.print', $foreignPrescription), route('prescriptions.print', $missingId), []],
            ['GET', route('prescriptions.pdf', $foreignPrescription), route('prescriptions.pdf', $missingId), []],
            ['POST', route('prescriptions.send-email', $foreignPrescription), route('prescriptions.send-email', $missingId), []],
            ['PUT', route('prescriptions.update', $foreignPrescription), route('prescriptions.update', $missingId), $this->updatePayload($foreignPrescription)],
            ['DELETE', route('prescriptions.destroy', $foreignPrescription), route('prescriptions.destroy', $missingId), []],
            ['POST', route('prescriptions.sign', $foreignPrescription), route('prescriptions.sign', $missingId), []],
        ];

        foreach ($requests as [$method, $foreignUrl, $missingUrl, $payload]) {
            $foreignJson = $this->actingAs($actor)
                ->withSession($this->confirmedSession())
                ->json($method, $foreignUrl, $payload);
            $missingJson = $this->actingAs($actor)
                ->withSession($this->confirmedSession())
                ->json($method, $missingUrl, $payload);

            $this->assertControlledError($foreignJson, 404, 'RESOURCE_NOT_FOUND');
            $this->assertControlledError($missingJson, 404, 'RESOURCE_NOT_FOUND');
            $this->assertSame($foreignJson->json(), $missingJson->json());
        }

        $audits = AuditLog::where('auditable_id', $foreignPrescription->id)->get();
        $this->assertCount(9, $audits);
        $this->assertTrue($audits->every(
            fn (AuditLog $audit): bool => (int) $audit->clinic_id === (int) $targetClinic->id,
        ));
        $this->assertTrue($audits->every(
            fn (AuditLog $audit): bool => ($audit->new_values['reason_code'] ?? null) === 'wrong_clinic',
        ));
        $this->assertDatabaseMissing('audit_logs', [
            'clinic_id' => $sourceClinic->id,
            'auditable_id' => $foreignPrescription->id,
        ]);
    }

    public function test_source_administrator_cannot_view_cross_clinic_denial_audit(): void
    {
        $sourceClinic = $this->clinic();
        $targetClinic = $this->clinic();
        $administrator = $this->userForClinic($sourceClinic, 'administrador');
        $targetOwner = $this->userForClinic($targetClinic, 'medico');
        $foreignPrescription = $this->prescriptionFor($targetClinic, $targetOwner);

        $response = $this->actingAs($administrator)
            ->withSession($this->confirmedSession())
            ->postJson(route('prescriptions.sign', $foreignPrescription));

        $this->assertControlledError($response, 404, 'RESOURCE_NOT_FOUND');
        $audit = AuditLog::where('action', 'prescriptions.sign')
            ->where('auditable_id', $foreignPrescription->id)
            ->sole();
        $this->assertSame($targetClinic->id, $audit->clinic_id);
        $this->assertSame('wrong_clinic', $audit->new_values['reason_code']);
        $this->assertDatabaseMissing('audit_logs', [
            'clinic_id' => $sourceClinic->id,
            'action' => 'prescriptions.sign',
            'auditable_id' => $foreignPrescription->id,
        ]);

        $this->actingAs($administrator)
            ->get(route('audit-logs.index', ['action' => 'prescriptions.sign']))
            ->assertOk()
            ->assertDontSee('prescriptions.sign');
    }

    public function test_missing_permissions_and_invalid_context_use_controlled_403_json(): void
    {
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico');
        $actor = $this->userForClinic($clinic, 'recepcionista');
        $prescription = $this->prescriptionFor($clinic, $owner);

        $requests = [
            $this->actingAs($actor)->getJson(route('prescriptions.show', $prescription)),
            $this->actingAs($actor)->putJson(
                route('prescriptions.update', $prescription),
                $this->updatePayload($prescription),
            ),
            $this->actingAs($actor)->deleteJson(route('prescriptions.destroy', $prescription)),
            $this->actingAs($actor)
                ->withSession($this->confirmedSession())
                ->postJson(route('prescriptions.sign', $prescription)),
        ];

        foreach ($requests as $response) {
            $this->assertControlledError($response, 403, 'OPERATION_NOT_AUTHORIZED');
        }

        $inactiveOwner = $this->userForClinic($clinic, 'medico', 'inactive');
        $inactivePrescription = $this->prescriptionFor($clinic, $inactiveOwner);
        $this->assertControlledError(
            $this->actingAs($inactiveOwner)
                ->withSession($this->confirmedSession())
                ->postJson(route('prescriptions.sign', $inactivePrescription)),
            403,
            'OPERATION_NOT_AUTHORIZED',
        );

        $detachedOwner = $this->userForClinic($clinic, 'medico');
        $detachedPrescription = $this->prescriptionFor($clinic, $detachedOwner);
        $detachedOwner->clinics()->detach($clinic->id);
        $this->assertControlledError(
            $this->actingAs($detachedOwner)
                ->withSession($this->confirmedSession())
                ->postJson(route('prescriptions.sign', $detachedPrescription)),
            403,
            'OPERATION_NOT_AUTHORIZED',
        );

        $inactiveClinic = $this->clinic();
        $inactiveClinicOwner = $this->userForClinic($inactiveClinic, 'medico');
        $inactiveClinicPrescription = $this->prescriptionFor($inactiveClinic, $inactiveClinicOwner);
        $inactiveClinic->update(['status' => 'inactive']);
        $this->assertControlledError(
            $this->actingAs($inactiveClinicOwner)
                ->withSession($this->confirmedSession())
                ->postJson(route('prescriptions.sign', $inactiveClinicPrescription)),
            403,
            'OPERATION_NOT_AUTHORIZED',
        );
    }

    public function test_doctor_with_appointment_or_consultation_returns_controlled_conflict_on_delete(): void
    {
        $clinic = $this->clinic();
        $administrator = $this->userForClinic($clinic, 'administrador');
        $doctorUser = $this->userForClinic($clinic, 'medico');
        $doctor = $this->doctorFor($clinic, $doctorUser);
        $patient = Patient::factory()->for($clinic)->create();
        $appointment = Appointment::factory()
            ->for($clinic)
            ->for($patient)
            ->for($doctor)
            ->create();
        $consultation = Consultation::factory()
            ->for($patient)
            ->for($doctor)
            ->create();

        $response = $this->actingAs($administrator)
            ->deleteJson(route('doctors.destroy', $doctor));

        $this->assertControlledError($response, 409, 'DOCTOR_REQUIRES_DEACTIVATION');
        $this->assertDatabaseHas('doctors', ['id' => $doctor->id]);
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id]);
        $this->assertDatabaseHas('consultations', ['id' => $consultation->id]);
    }

    public function test_signer_foreign_key_migration_round_trip_restores_restrict(): void
    {
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico');
        $prescription = $this->prescriptionFor($clinic, $owner);
        $this->signPrescription($owner, $prescription);
        $signedByUserId = $prescription->refresh()->signed_by_user_id;
        $migration = require database_path(
            'migrations/2026_07_21_000001_restrict_signed_prescription_user_deletion.php',
        );

        $migration->down();
        $migration->up();

        $rejected = false;

        try {
            DB::table('users')->where('id', $owner->id)->delete();
        } catch (QueryException) {
            $rejected = true;
        }

        $this->assertTrue($rejected);
        $this->assertDatabaseHas('users', ['id' => $owner->id]);
        $this->assertSame($signedByUserId, $prescription->refresh()->signed_by_user_id);
    }

    public function test_update_and_delete_roll_back_with_controlled_error_when_audit_storage_is_unavailable(): void
    {
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico');
        $administrator = $this->userForClinic($clinic, 'administrador');
        $prescription = $this->prescriptionFor($clinic, $owner);
        $originalInstructions = $prescription->general_instructions;
        $originalItemId = $prescription->items()->sole()->id;
        Schema::drop('audit_logs');

        $updateResponse = $this->actingAs($owner)->putJson(
            route('prescriptions.update', $prescription),
            $this->updatePayload($prescription, [
                'general_instructions' => 'C02ROLLBACKUPDATE',
                'items' => [['medication_name' => 'C02ROLLBACKITEM']],
            ]),
        );

        $this->assertControlledError($updateResponse, 500, 'OPERATION_FAILED');
        $this->assertSame($originalInstructions, $prescription->refresh()->general_instructions);
        $this->assertDatabaseHas('prescription_items', ['id' => $originalItemId]);
        $this->assertDatabaseMissing('prescription_items', ['medication_name' => 'C02ROLLBACKITEM']);

        $deleteResponse = $this->actingAs($administrator)
            ->deleteJson(route('prescriptions.destroy', $prescription));

        $this->assertControlledError($deleteResponse, 500, 'OPERATION_FAILED');
        $this->assertDatabaseHas('prescriptions', ['id' => $prescription->id]);
        $this->assertDatabaseHas('prescription_items', ['id' => $originalItemId]);
    }

    private function clinic(): Clinic
    {
        return Clinic::factory()->create(['status' => 'active']);
    }

    private function userForClinic(
        Clinic $clinic,
        string $role,
        string $status = 'active',
    ): User {
        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
            'current_clinic_id' => $clinic->id,
            'status' => $status,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function doctorFor(Clinic $clinic, User $user): Doctor
    {
        return Doctor::factory()
            ->for($clinic)
            ->for($user)
            ->for(Specialty::factory()->create(['status' => 'active']))
            ->create(['status' => 'active']);
    }

    private function prescriptionFor(Clinic $clinic, User $owner): Prescription
    {
        $patient = Patient::factory()->for($clinic)->create([
            'first_name' => 'C02HARDENINGPATIENT',
            'last_name' => 'Synthetic',
            'email' => 'c02-hardening@example.invalid',
        ]);
        $doctor = $this->doctorFor($clinic, $owner);
        $prescription = Prescription::factory()
            ->for($patient)
            ->for($doctor)
            ->create([
                'general_instructions' => 'C02HARDENINGINSTRUCTION',
                'status' => 'active',
            ]);
        PrescriptionItem::factory()->for($prescription)->create([
            'medication_name' => 'C02HARDENINGMEDICATION',
            'instructions' => 'C02HARDENINGINSTRUCTION',
        ]);

        return $prescription;
    }

    private function updatePayload(Prescription $prescription, array $overrides = []): array
    {
        return array_merge([
            'patient_id' => $prescription->patient_id,
            'doctor_id' => $prescription->doctor_id,
            'consultation_id' => null,
            'prescription_date' => $prescription->prescription_date?->format('Y-m-d') ?? '2026-07-21',
            'general_instructions' => 'C02HARDENINGUPDATE',
            'status' => 'active',
            'items' => [[
                'medication_name' => 'C02HARDENINGITEM',
                'dosage' => 'synthetic',
                'frequency' => 'synthetic',
                'duration' => 'synthetic',
                'instructions' => 'C02HARDENINGITEMINSTRUCTION',
            ]],
        ], $overrides);
    }

    private function confirmedSession(): array
    {
        return ['auth.password_confirmed_at' => time()];
    }

    private function signPrescription(User $owner, Prescription $prescription): void
    {
        $this->actingAs($owner)
            ->withSession($this->confirmedSession())
            ->post(route('prescriptions.sign', $prescription))
            ->assertRedirect(route('prescriptions.show', $prescription));

        $this->assertTrue($prescription->refresh()->isSigned());
    }

    private function assertControlledError(
        TestResponse $response,
        int $status,
        string $code,
    ): void {
        $response->assertStatus($status)->assertExactJson([
            'message' => 'No se pudo completar la operación.',
            'code' => $code,
        ]);
    }
}
