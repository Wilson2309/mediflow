<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class PrescriptionSecurityClosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_other_doctor_cannot_reassign_update_or_then_sign_owners_prescription(): void
    {
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico');
        $otherDoctorUser = $this->userForClinic($clinic, 'medico');
        $prescription = $this->prescriptionFor($clinic, $owner);
        $otherDoctor = $this->doctorFor($clinic, $otherDoctorUser);
        $otherPatient = Patient::factory()->for($clinic)->create();
        $original = [
            'doctor_id' => $prescription->doctor_id,
            'patient_id' => $prescription->patient_id,
            'general_instructions' => $prescription->general_instructions,
        ];
        $originalItemId = $prescription->items()->sole()->id;

        $this->actingAs($otherDoctorUser)
            ->put(route('prescriptions.update', $prescription), $this->updatePayload($prescription, [
                'doctor_id' => $otherDoctor->id,
                'patient_id' => $otherPatient->id,
                'general_instructions' => 'C02UPDATEBYPASSMARKER',
                'items' => [['medication_name' => 'C02BYPASSITEMMARKER']],
            ]))
            ->assertForbidden();

        $prescription->refresh();
        $this->assertSame($original['doctor_id'], $prescription->doctor_id);
        $this->assertSame($original['patient_id'], $prescription->patient_id);
        $this->assertSame($original['general_instructions'], $prescription->general_instructions);
        $this->assertDatabaseHas('prescription_items', ['id' => $originalItemId]);
        $this->assertDatabaseMissing('prescription_items', ['medication_name' => 'C02BYPASSITEMMARKER']);

        $this->actingAs($otherDoctorUser)
            ->withSession($this->confirmedSession())
            ->post(route('prescriptions.sign', $prescription))
            ->assertForbidden();

        $this->assertUnsigned($prescription);
    }

    public function test_administrator_cannot_change_prescription_identity_or_clinical_content(): void
    {
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico');
        $administrator = $this->userForClinic($clinic, 'administrador');
        $otherDoctorUser = $this->userForClinic($clinic, 'medico');
        $otherDoctor = $this->doctorFor($clinic, $otherDoctorUser);
        $prescription = $this->prescriptionFor($clinic, $owner);
        $originalDoctorId = $prescription->doctor_id;
        $originalInstructions = $prescription->general_instructions;

        $this->actingAs($administrator)
            ->put(route('prescriptions.update', $prescription), $this->updatePayload($prescription, [
                'doctor_id' => $otherDoctor->id,
                'general_instructions' => 'C02ADMINUPDATEMARKER',
            ]))
            ->assertForbidden();

        $prescription->refresh();
        $this->assertSame($originalDoctorId, $prescription->doctor_id);
        $this->assertSame($originalInstructions, $prescription->general_instructions);
        $this->assertDatabaseMissing('prescriptions', [
            'id' => $prescription->id,
            'general_instructions' => 'C02ADMINUPDATEMARKER',
        ]);
    }

    public function test_owner_update_ignores_identity_fields_uses_allowlist_and_audits_without_phi(): void
    {
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico');
        $prescription = $this->prescriptionFor($clinic, $owner);
        $otherDoctorUser = $this->userForClinic($clinic, 'medico');
        $otherDoctor = $this->doctorFor($clinic, $otherDoctorUser);
        $otherPatient = Patient::factory()->for($clinic)->create();
        $originalDoctorId = $prescription->doctor_id;
        $originalPatientId = $prescription->patient_id;
        $originalItemId = $prescription->items()->sole()->id;

        $this->actingAs($owner)
            ->get(route('prescriptions.edit', $prescription))
            ->assertOk()
            ->assertDontSee('name="doctor_id"', escape: false)
            ->assertDontSee('name="patient_id"', escape: false);

        $this->actingAs($owner)
            ->putJson(route('prescriptions.update', $prescription), $this->updatePayload($prescription, [
                'doctor_id' => $otherDoctor->id,
                'patient_id' => $otherPatient->id,
                'general_instructions' => 'C02NEWCLINICALMARKER',
                'items' => [['medication_name' => 'C02NEWITEMMARKER']],
            ]))
            ->assertOk()
            ->assertExactJson([
                'message' => 'Operación completada correctamente.',
                'code' => 'OPERATION_COMPLETED',
            ]);

        $prescription->refresh();
        $this->assertSame($originalDoctorId, $prescription->doctor_id);
        $this->assertSame($originalPatientId, $prescription->patient_id);
        $this->assertSame('C02NEWCLINICALMARKER', $prescription->general_instructions);
        $this->assertDatabaseMissing('prescription_items', ['id' => $originalItemId]);
        $this->assertDatabaseHas('prescription_items', [
            'prescription_id' => $prescription->id,
            'medication_name' => 'C02NEWITEMMARKER',
        ]);

        $audit = AuditLog::where('action', 'prescription.updated')
            ->where('auditable_id', $prescription->id)
            ->sole();
        $serializedAudit = strtolower((string) json_encode([
            $audit->old_values,
            $audit->new_values,
            $audit->description,
        ]));

        foreach (['c02newclinicalmarker', 'c02newitemmarker', 'medication_name', 'general_instructions'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $serializedAudit);
        }
    }

    public function test_signed_and_partially_signed_prescriptions_cannot_be_updated(): void
    {
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico');
        $signed = $this->prescriptionFor($clinic, $owner);
        $this->signPrescription($owner, $signed);
        $signedInstructions = $signed->refresh()->general_instructions;

        $this->actingAs($owner)
            ->putJson(route('prescriptions.update', $signed), $this->updatePayload($signed, [
                'general_instructions' => 'C02SIGNEDUPDATEMARKER',
            ]))
            ->assertStatus(409)
            ->assertExactJson([
                'message' => 'No se pudo completar la operación.',
                'code' => 'RESOURCE_STATE_CONFLICT',
            ]);

        $this->assertSame($signedInstructions, $signed->refresh()->general_instructions);

        $partial = $this->prescriptionFor($clinic, $owner);
        $partialCode = 'C02-PARTIAL-'.Str::upper(Str::random(8));
        $partial->forceFill(['signature_verification_code' => $partialCode])->save();
        $partialInstructions = $partial->general_instructions;

        $this->actingAs($owner)
            ->putJson(route('prescriptions.update', $partial), $this->updatePayload($partial, [
                'general_instructions' => 'C02PARTIALUPDATEMARKER',
            ]))
            ->assertStatus(409)
            ->assertExactJson([
                'message' => 'No se pudo completar la operación.',
                'code' => 'RESOURCE_STATE_CONFLICT',
            ]);

        $partial->refresh();
        $this->assertSame($partialCode, $partial->signature_verification_code);
        $this->assertSame($partialInstructions, $partial->general_instructions);
    }

    public function test_signed_and_partial_prescriptions_cannot_be_deleted_and_denial_audit_has_no_phi(): void
    {
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico');
        $administrator = $this->userForClinic($clinic, 'administrador');
        $signed = $this->prescriptionFor($clinic, $owner);
        $signedItemId = $signed->items()->sole()->id;
        $this->signPrescription($owner, $signed);

        $this->actingAs($administrator)
            ->get(route('prescriptions.index'))
            ->assertOk()
            ->assertDontSee('<form method="POST" action="'.route('prescriptions.destroy', $signed).'"', escape: false);

        $this->actingAs($administrator)
            ->from(route('prescriptions.index'))
            ->delete(route('prescriptions.destroy', $signed))
            ->assertRedirect(route('prescriptions.index'))
            ->assertSessionHas('error', 'No se puede eliminar esta receta.');

        $this->actingAs($administrator)
            ->deleteJson(route('prescriptions.destroy', $signed))
            ->assertStatus(409)
            ->assertExactJson([
                'message' => 'No se pudo completar la operación.',
                'code' => 'RESOURCE_STATE_CONFLICT',
            ]);

        $this->assertDatabaseHas('prescriptions', ['id' => $signed->id]);
        $this->assertDatabaseHas('prescription_items', ['id' => $signedItemId]);

        $partial = $this->prescriptionFor($clinic, $owner);
        $partialItemId = $partial->items()->sole()->id;
        $partial->forceFill(['signed_at' => now()])->save();

        $this->actingAs($administrator)
            ->deleteJson(route('prescriptions.destroy', $partial))
            ->assertStatus(409)
            ->assertExactJson([
                'message' => 'No se pudo completar la operación.',
                'code' => 'RESOURCE_STATE_CONFLICT',
            ]);

        $this->assertDatabaseHas('prescriptions', ['id' => $partial->id]);
        $this->assertDatabaseHas('prescription_items', ['id' => $partialItemId]);

        $audits = AuditLog::where('action', 'prescription.delete_denied')->get();
        $this->assertCount(3, $audits);
        $serializedAudit = strtolower((string) json_encode($audits->map(fn (AuditLog $audit): array => [
            $audit->old_values,
            $audit->new_values,
            $audit->description,
        ])->all()));

        foreach (['c02patientmarker', 'c02medicationmarker', 'c02instructionmarker', 'signature_hash'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $serializedAudit);
        }
    }

    public function test_cross_clinic_and_missing_prescriptions_share_external_404_but_audit_keeps_internal_reason(): void
    {
        $clinic = $this->clinic();
        $otherClinic = $this->clinic();
        $actor = $this->userForClinic($clinic, 'medico');
        $otherOwner = $this->userForClinic($otherClinic, 'medico');
        $foreignPrescription = $this->prescriptionFor($otherClinic, $otherOwner);
        $missingId = (int) Prescription::max('id') + 1000;

        $this->actingAs($actor)
            ->withSession($this->confirmedSession())
            ->post(route('prescriptions.sign', $foreignPrescription))
            ->assertNotFound();
        $this->actingAs($actor)
            ->withSession($this->confirmedSession())
            ->post(route('prescriptions.sign', $missingId))
            ->assertNotFound();

        $foreignJson = $this->actingAs($actor)
            ->withSession($this->confirmedSession())
            ->postJson(route('prescriptions.sign', $foreignPrescription));
        $missingJson = $this->actingAs($actor)
            ->withSession($this->confirmedSession())
            ->postJson(route('prescriptions.sign', $missingId));

        $foreignJson->assertNotFound()->assertExactJson([
            'message' => 'No se pudo completar la operación.',
            'code' => 'RESOURCE_NOT_FOUND',
        ]);
        $missingJson->assertNotFound()->assertExactJson([
            'message' => 'No se pudo completar la operación.',
            'code' => 'RESOURCE_NOT_FOUND',
        ]);
        $this->assertSame($foreignJson->json(), $missingJson->json());
        $this->assertStringNotContainsString('wrong_clinic', strtolower($foreignJson->getContent()));

        $audits = AuditLog::where('action', 'prescriptions.sign')
            ->where('auditable_id', $foreignPrescription->id)
            ->get();
        $this->assertCount(2, $audits);
        $this->assertTrue($audits->every(
            fn (AuditLog $audit): bool => $audit->new_values['reason_code'] === 'wrong_clinic',
        ));
        $serializedAudit = strtolower((string) json_encode($audits->pluck('new_values')->all()));
        foreach (['c02patientmarker', 'c02medicationmarker', 'c02instructionmarker'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $serializedAudit);
        }

        $this->actingAs($actor)->get(route('audit-logs.index'))->assertForbidden();
    }

    public function test_signing_json_contract_covers_403_409_422_423_and_200(): void
    {
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico');
        $otherDoctor = $this->userForClinic($clinic, 'medico');

        $forbiddenPrescription = $this->prescriptionFor($clinic, $owner);
        $this->actingAs($otherDoctor)
            ->withSession($this->confirmedSession())
            ->postJson(route('prescriptions.sign', $forbiddenPrescription))
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'No se pudo completar la operación.',
                'code' => 'OPERATION_NOT_AUTHORIZED',
            ]);
        $this->assertUnsigned($forbiddenPrescription);

        $reauthPrescription = $this->prescriptionFor($clinic, $owner);
        $this->actingAs($owner)
            ->withSession(['auth.password_confirmed_at' => 0])
            ->postJson(route('prescriptions.sign', $reauthPrescription))
            ->assertStatus(423)
            ->assertExactJson([
                'message' => 'No se pudo completar la operación.',
                'code' => 'RECENT_AUTHENTICATION_REQUIRED',
            ]);
        $this->assertUnsigned($reauthPrescription);

        $validationPrescription = $this->prescriptionFor($clinic, $owner);
        $this->actingAs($owner)
            ->putJson(route('prescriptions.update', $validationPrescription), $this->updatePayload(
                $validationPrescription,
                ['status' => 'invalid'],
            ))
            ->assertUnprocessable()
            ->assertJson([
                'message' => 'No se pudo completar la operación.',
                'code' => 'VALIDATION_ERROR',
            ])
            ->assertJsonStructure(['message', 'code', 'errors' => ['status']]);
        $this->assertSame('active', $validationPrescription->refresh()->status);

        $successPrescription = $this->prescriptionFor($clinic, $owner);
        $this->actingAs($owner)
            ->withSession($this->confirmedSession())
            ->postJson(route('prescriptions.sign', $successPrescription))
            ->assertOk()
            ->assertExactJson([
                'message' => 'Operación completada correctamente.',
                'code' => 'OPERATION_COMPLETED',
            ]);
        $this->assertTrue($successPrescription->refresh()->isSigned());

        $this->actingAs($owner)
            ->withSession($this->confirmedSession())
            ->postJson(route('prescriptions.sign', $successPrescription))
            ->assertStatus(409)
            ->assertExactJson([
                'message' => 'No se pudo completar la operación.',
                'code' => 'RESOURCE_STATE_CONFLICT',
            ]);
    }

    public function test_signer_cannot_be_deleted_can_be_inactivated_and_database_fk_preserves_attribution(): void
    {
        $clinic = $this->clinic();
        $syntheticPassword = Str::random(40);
        $owner = $this->userForClinic($clinic, 'medico');
        $owner->forceFill(['password' => Hash::make($syntheticPassword)])->save();
        $administrator = $this->userForClinic($clinic, 'administrador');
        $prescription = $this->prescriptionFor($clinic, $owner);
        $this->signPrescription($owner, $prescription);
        $signedByUserId = $prescription->refresh()->signed_by_user_id;

        $this->actingAs($owner)
            ->delete(route('profile.destroy'), ['password' => $syntheticPassword])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrorsIn('userDeletion', 'password');
        $this->assertDatabaseHas('users', ['id' => $owner->id]);

        $this->actingAs($administrator)
            ->from(route('users.show', $owner))
            ->delete(route('users.destroy', $owner))
            ->assertRedirect(route('users.show', $owner))
            ->assertSessionHas('error', 'El usuario no puede eliminarse. Inactívelo para conservar la trazabilidad.');

        $this->actingAs($administrator)
            ->deleteJson(route('users.destroy', $owner))
            ->assertStatus(409)
            ->assertExactJson([
                'message' => 'No se pudo completar la operación.',
                'code' => 'USER_REQUIRES_DEACTIVATION',
            ]);

        $this->assertDatabaseHas('users', ['id' => $owner->id]);
        $this->assertSame($signedByUserId, $prescription->refresh()->signed_by_user_id);

        $this->actingAs($administrator)
            ->put(route('users.update', $owner), $this->userUpdatePayload($owner, [
                'status' => 'inactive',
                'doctor_status' => 'inactive',
            ]))
            ->assertRedirect(route('users.show', $owner));
        $this->assertSame('inactive', $owner->refresh()->status);
        $this->assertSame($signedByUserId, $prescription->refresh()->signed_by_user_id);

        $foreignKeyRejectedDelete = false;

        try {
            DB::table('users')->where('id', $owner->id)->delete();
        } catch (QueryException) {
            $foreignKeyRejectedDelete = true;
        }

        $this->assertTrue($foreignKeyRejectedDelete);
        $this->assertDatabaseHas('users', ['id' => $owner->id]);
        $this->assertSame($signedByUserId, $prescription->refresh()->signed_by_user_id);
    }

    public function test_doctor_with_prescription_cannot_be_deleted_but_can_be_inactivated(): void
    {
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico');
        $administrator = $this->userForClinic($clinic, 'administrador');
        $prescription = $this->prescriptionFor($clinic, $owner);
        $this->signPrescription($owner, $prescription);
        $doctor = $prescription->doctor;

        $this->actingAs($administrator)
            ->from(route('doctors.show', $doctor))
            ->delete(route('doctors.destroy', $doctor))
            ->assertRedirect(route('doctors.show', $doctor))
            ->assertSessionHas('error', 'El médico no puede eliminarse. Inactívelo para conservar la trazabilidad.');

        $this->actingAs($administrator)
            ->deleteJson(route('doctors.destroy', $doctor))
            ->assertStatus(409)
            ->assertExactJson([
                'message' => 'No se pudo completar la operación.',
                'code' => 'DOCTOR_REQUIRES_DEACTIVATION',
            ]);
        $this->assertDatabaseHas('doctors', ['id' => $doctor->id]);

        $this->actingAs($administrator)
            ->put(route('doctors.update', $doctor), $this->doctorUpdatePayload($doctor, [
                'status' => 'inactive',
            ]))
            ->assertRedirect(route('doctors.show', $doctor));
        $this->assertSame('inactive', $doctor->refresh()->status);
        $this->assertDatabaseHas('prescriptions', ['id' => $prescription->id]);
    }

    public function test_signature_attribution_fields_are_immutable_after_signing(): void
    {
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico');
        $otherUser = $this->userForClinic($clinic, 'medico');
        $prescription = $this->prescriptionFor($clinic, $owner);
        $this->signPrescription($owner, $prescription);
        $prescription->refresh();
        $originalSignedAt = $prescription->getRawOriginal('signed_at');
        $originalSigner = $prescription->signed_by_user_id;
        $rejected = false;

        try {
            $prescription->forceFill([
                'signed_at' => now()->addMinute(),
                'signed_by_user_id' => $otherUser->id,
            ])->save();
        } catch (LogicException) {
            $rejected = true;
        }

        $this->assertTrue($rejected);
        $prescription->refresh();
        $this->assertSame($originalSignedAt, $prescription->getRawOriginal('signed_at'));
        $this->assertSame($originalSigner, $prescription->signed_by_user_id);
    }

    private function clinic(): Clinic
    {
        return Clinic::factory()->create(['status' => 'active']);
    }

    private function userForClinic(Clinic $clinic, string $role): User
    {
        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
            'current_clinic_id' => $clinic->id,
            'status' => 'active',
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
            'first_name' => 'C02PATIENTMARKER',
            'last_name' => 'Synthetic',
            'email' => 'c02.synthetic@example.invalid',
        ]);
        $doctor = $this->doctorFor($clinic, $owner);
        $prescription = Prescription::factory()
            ->for($patient)
            ->for($doctor)
            ->create([
                'general_instructions' => 'C02INSTRUCTIONMARKER',
                'status' => 'active',
            ]);
        PrescriptionItem::factory()->for($prescription)->create([
            'medication_name' => 'C02MEDICATIONMARKER',
            'instructions' => 'C02INSTRUCTIONMARKER',
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
            'general_instructions' => 'C02UPDATEDEFAULTMARKER',
            'status' => 'active',
            'items' => [[
                'medication_name' => 'C02UPDATEITEMMARKER',
                'dosage' => 'synthetic',
                'frequency' => 'synthetic',
                'duration' => 'synthetic',
                'instructions' => 'C02UPDATEINSTRUCTIONMARKER',
            ]],
        ], $overrides);
    }

    private function userUpdatePayload(User $user, array $overrides = []): array
    {
        return array_merge([
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'password' => '',
            'password_confirmation' => '',
            'role' => $user->getRoleNames()->first(),
            'status' => $user->status,
            'specialty_id' => $user->doctor?->specialty_id,
            'license_number' => $user->doctor?->license_number,
            'doctor_phone' => $user->doctor?->phone,
            'consultation_fee' => $user->doctor?->consultation_fee ?? '0.00',
            'doctor_status' => $user->doctor?->status ?? 'active',
        ], $overrides);
    }

    private function doctorUpdatePayload(Doctor $doctor, array $overrides = []): array
    {
        return array_merge([
            'name' => $doctor->user?->name,
            'email' => $doctor->user?->email,
            'password' => null,
            'password_confirmation' => null,
            'specialty_id' => $doctor->specialty_id,
            'license_number' => $doctor->license_number,
            'phone' => $doctor->phone,
            'consultation_fee' => $doctor->consultation_fee,
            'status' => $doctor->status,
        ], $overrides);
    }

    private function signPrescription(User $owner, Prescription $prescription): void
    {
        $this->actingAs($owner)
            ->withSession($this->confirmedSession())
            ->post(route('prescriptions.sign', $prescription))
            ->assertRedirect(route('prescriptions.show', $prescription));

        $this->assertTrue($prescription->refresh()->isSigned());
    }

    private function confirmedSession(): array
    {
        return ['auth.password_confirmed_at' => time()];
    }

    private function assertUnsigned(Prescription $prescription): void
    {
        $prescription->refresh();
        $this->assertNull($prescription->signed_at);
        $this->assertNull($prescription->signed_by_user_id);
        $this->assertNull($prescription->signature_verification_code);
        $this->assertNull($prescription->signature_hash);
    }
}
