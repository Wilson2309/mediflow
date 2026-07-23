<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PrescriptionConsultationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_update_preserves_all_document_identity_fields_and_updates_only_allowed_content(): void
    {
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico');
        $doctor = $this->doctorFor($clinic, $owner);
        $patient = $this->patientFor($clinic, 'IDENTITYOWNER');
        $consultation = $this->consultationFor($patient, $doctor, '2026-07-22 08:00:00');
        $prescription = $this->prescriptionFor($patient, $doctor, $consultation);
        $otherOwner = $this->userForClinic($clinic, 'medico');
        $otherDoctor = $this->doctorFor($clinic, $otherOwner);
        $otherPatient = $this->patientFor($clinic, 'IDENTITYOTHER');
        $otherConsultation = $this->consultationFor($otherPatient, $otherDoctor, '2026-07-22 09:00:00');
        $originalIdentity = [
            'patient_id' => $prescription->patient_id,
            'doctor_id' => $prescription->doctor_id,
            'consultation_id' => $prescription->consultation_id,
        ];

        $this->actingAs($owner)
            ->putJson(route('prescriptions.update', $prescription), $this->updatePayload($prescription, [
                'patient_id' => $otherPatient->id,
                'doctor_id' => $otherDoctor->id,
                'consultation_id' => $otherConsultation->id,
                'general_instructions' => 'PHASE512_ALLOWED_CONTENT',
            ]))
            ->assertOk()
            ->assertJsonPath('code', 'OPERATION_COMPLETED');

        $prescription->refresh();
        $this->assertSame($originalIdentity['patient_id'], $prescription->patient_id);
        $this->assertSame($originalIdentity['doctor_id'], $prescription->doctor_id);
        $this->assertSame($originalIdentity['consultation_id'], $prescription->consultation_id);
        $this->assertSame('PHASE512_ALLOWED_CONTENT', $prescription->general_instructions);
        $this->assertDatabaseHas('prescriptions', [
            'id' => $prescription->id,
            ...$originalIdentity,
        ]);
    }

    public function test_model_rejects_ordinary_changes_to_each_document_identity_field(): void
    {
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico');
        $doctor = $this->doctorFor($clinic, $owner);
        $patient = $this->patientFor($clinic, 'MODELIDENTITY');
        $consultation = $this->consultationFor($patient, $doctor, '2026-07-22 10:00:00');
        $prescription = $this->prescriptionFor($patient, $doctor, $consultation);
        $otherOwner = $this->userForClinic($clinic, 'medico');
        $otherDoctor = $this->doctorFor($clinic, $otherOwner);
        $otherPatient = $this->patientFor($clinic, 'MODELIDENTITYOTHER');
        $otherConsultation = $this->consultationFor($otherPatient, $otherDoctor, '2026-07-22 11:00:00');
        $replacements = [
            'patient_id' => $otherPatient->id,
            'doctor_id' => $otherDoctor->id,
            'consultation_id' => $otherConsultation->id,
        ];

        foreach ($replacements as $attribute => $replacement) {
            $prescription->refresh();
            $original = $prescription->getAttribute($attribute);
            $rejected = false;

            try {
                $prescription->forceFill([$attribute => $replacement])->save();
            } catch (LogicException) {
                $rejected = true;
            }

            $this->assertTrue($rejected, $attribute.' must be immutable.');
            $this->assertSame($original, $prescription->refresh()->getAttribute($attribute));
        }
    }

    public function test_edit_form_has_no_consultation_input_and_does_not_expose_unrelated_consultations(): void
    {
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico');
        $doctor = $this->doctorFor($clinic, $owner);
        $patient = $this->patientFor($clinic, 'EDITOWNER');
        $consultation = $this->consultationFor($patient, $doctor, '2026-07-22 12:00:00');
        $prescription = $this->prescriptionFor($patient, $doctor, $consultation);
        $otherPatient = $this->patientFor($clinic, 'EDITUNRELATED');
        $this->consultationFor($otherPatient, $doctor, '2035-12-31 23:59:00');

        $this->actingAs($owner)
            ->get(route('prescriptions.edit', $prescription))
            ->assertOk()
            ->assertDontSee('name="consultation_id"', escape: false)
            ->assertSee('22/07/2026 12:00')
            ->assertDontSee('31/12/2035 23:59');
    }

    public function test_create_form_only_offers_an_explicitly_authorized_owner_consultation(): void
    {
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico');
        $doctor = $this->doctorFor($clinic, $owner);
        $patient = $this->patientFor($clinic, 'CREATEOWNER');
        $allowed = $this->consultationFor($patient, $doctor, '2026-07-22 13:00:00');
        $otherOwner = $this->userForClinic($clinic, 'medico');
        $otherDoctor = $this->doctorFor($clinic, $otherOwner);
        $otherPatient = $this->patientFor($clinic, 'CREATEOTHER');
        $other = $this->consultationFor($otherPatient, $otherDoctor, '2035-12-30 23:58:00');

        $this->actingAs($owner)
            ->get(route('prescriptions.create'))
            ->assertOk()
            ->assertDontSee('22/07/2026 13:00')
            ->assertDontSee('30/12/2035 23:58');

        $this->actingAs($owner)
            ->get(route('prescriptions.create', ['consultation_id' => $allowed->id]))
            ->assertOk()
            ->assertSee('22/07/2026 13:00')
            ->assertDontSee('30/12/2035 23:58');

        $this->actingAs($owner)
            ->get(route('prescriptions.create', ['consultation_id' => $other->id]))
            ->assertNotFound();
    }

    public function test_create_rejects_consultation_from_another_clinic(): void
    {
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico');
        $doctor = $this->doctorFor($clinic, $owner);
        $patient = $this->patientFor($clinic, 'CROSSCLINICCREATE');
        $otherClinic = $this->clinic();
        $otherOwner = $this->userForClinic($otherClinic, 'medico');
        $otherDoctor = $this->doctorFor($otherClinic, $otherOwner);
        $otherPatient = $this->patientFor($otherClinic, 'CROSSCLINICOTHER');
        $foreignConsultation = $this->consultationFor($otherPatient, $otherDoctor, '2026-07-22 14:00:00');

        $this->actingAs($owner)
            ->from(route('prescriptions.create'))
            ->post(route('prescriptions.store'), $this->storePayload($patient, $doctor, $foreignConsultation))
            ->assertRedirect(route('prescriptions.create'))
            ->assertSessionHasErrors('clinic_id');

        $this->assertDatabaseCount('prescriptions', 0);
    }

    public function test_create_rejects_consultation_for_another_patient(): void
    {
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico');
        $doctor = $this->doctorFor($clinic, $owner);
        $patient = $this->patientFor($clinic, 'PATIENTCREATE');
        $otherPatient = $this->patientFor($clinic, 'PATIENTOTHER');
        $otherConsultation = $this->consultationFor($otherPatient, $doctor, '2026-07-22 15:00:00');

        $this->actingAs($owner)
            ->from(route('prescriptions.create'))
            ->post(route('prescriptions.store'), $this->storePayload($patient, $doctor, $otherConsultation))
            ->assertRedirect(route('prescriptions.create'))
            ->assertSessionHasErrors('consultation_id');

        $this->assertDatabaseCount('prescriptions', 0);
    }

    public function test_doctor_cannot_create_prescription_for_another_doctors_consultation(): void
    {
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico');
        $this->doctorFor($clinic, $owner);
        $otherOwner = $this->userForClinic($clinic, 'medico');
        $otherDoctor = $this->doctorFor($clinic, $otherOwner);
        $patient = $this->patientFor($clinic, 'DOCTORCREATE');
        $otherConsultation = $this->consultationFor($patient, $otherDoctor, '2026-07-22 16:00:00');

        $this->actingAs($owner)
            ->from(route('prescriptions.create'))
            ->post(route('prescriptions.store'), $this->storePayload($patient, $otherDoctor, $otherConsultation))
            ->assertRedirect(route('prescriptions.create'))
            ->assertSessionHasErrors('doctor_id');

        $this->assertDatabaseCount('prescriptions', 0);
    }

    public function test_signed_linked_consultation_delete_returns_json_conflict_and_preserves_integrity(): void
    {
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico');
        $administrator = $this->userForClinic($clinic, 'administrador');
        $doctor = $this->doctorFor($clinic, $owner);
        $patient = $this->patientFor($clinic, 'SIGNEDDELETEPATIENT');
        $consultation = $this->consultationFor($patient, $doctor, '2026-07-22 17:00:00', [
            'reason' => 'PHASE512_REASON_MARKER',
            'diagnosis' => 'PHASE512_DIAGNOSIS_MARKER',
            'blood_pressure' => 'PHASE512_BP_MARKER',
            'observations' => 'PHASE512_OBSERVATION_MARKER',
        ]);
        $prescription = $this->prescriptionFor($patient, $doctor, $consultation);
        $this->signPrescription($owner, $prescription);
        $prescription->refresh();
        $storedHash = (string) $prescription->signature_hash;
        $consultationId = $consultation->id;

        $this->actingAs($administrator)
            ->deleteJson(route('consultations.destroy', $consultation))
            ->assertStatus(409)
            ->assertExactJson([
                'message' => 'No se pudo completar la operación.',
                'code' => 'RESOURCE_STATE_CONFLICT',
            ]);

        $this->assertDatabaseHas('consultations', ['id' => $consultationId]);
        $this->assertDatabaseHas('prescriptions', [
            'id' => $prescription->id,
            'consultation_id' => $consultationId,
        ]);
        $prescription->refresh();
        $this->assertSame($consultationId, $prescription->consultation_id);
        $this->assertTrue(hash_equals($storedHash, $prescription->calculateSignatureHash()));

        $audit = AuditLog::where('action', 'consultation.delete_denied')
            ->where('auditable_id', $consultationId)
            ->sole();
        $this->assertSame([], $audit->old_values);
        $this->assertSame('denied', $audit->new_values['result']);
        $this->assertSame('has_prescriptions', $audit->new_values['reason_code']);
        $serializedAudit = strtolower((string) json_encode([
            $audit->old_values,
            $audit->new_values,
            $audit->description,
        ]));

        foreach (['phase512_reason_marker', 'phase512_diagnosis_marker', 'phase512_bp_marker', 'phase512_observation_marker', 'signature_hash'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $serializedAudit);
        }
    }

    public function test_unsigned_linked_consultation_delete_is_also_blocked_and_preserves_both_records(): void
    {
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico');
        $administrator = $this->userForClinic($clinic, 'administrador');
        $doctor = $this->doctorFor($clinic, $owner);
        $patient = $this->patientFor($clinic, 'UNSIGNEDDELETE');
        $consultation = $this->consultationFor($patient, $doctor, '2026-07-22 18:00:00');
        $prescription = $this->prescriptionFor($patient, $doctor, $consultation);

        $this->actingAs($administrator)
            ->from(route('consultations.index'))
            ->delete(route('consultations.destroy', $consultation))
            ->assertRedirect(route('consultations.index'))
            ->assertSessionHas('error', 'No se puede eliminar esta consulta.');

        $this->actingAs($administrator)
            ->deleteJson(route('consultations.destroy', $consultation))
            ->assertStatus(409)
            ->assertJsonPath('code', 'RESOURCE_STATE_CONFLICT');

        $this->assertDatabaseHas('consultations', ['id' => $consultation->id]);
        $this->assertDatabaseHas('prescriptions', [
            'id' => $prescription->id,
            'consultation_id' => $consultation->id,
        ]);
        $this->assertNull($prescription->refresh()->signed_at);
    }

    public function test_database_restrict_rejects_direct_model_and_query_deletes(): void
    {
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico');
        $doctor = $this->doctorFor($clinic, $owner);
        $patientA = $this->patientFor($clinic, 'FKMODELA');
        $consultationA = $this->consultationFor($patientA, $doctor, '2026-07-22 19:00:00');
        $prescriptionA = $this->prescriptionFor($patientA, $doctor, $consultationA);
        $patientB = $this->patientFor($clinic, 'FKQUERYB');
        $consultationB = $this->consultationFor($patientB, $doctor, '2026-07-22 20:00:00');
        $prescriptionB = $this->prescriptionFor($patientB, $doctor, $consultationB);
        $modelDeleteRejected = false;
        $queryDeleteRejected = false;

        try {
            $consultationA->delete();
        } catch (QueryException) {
            $modelDeleteRejected = true;
        }

        try {
            DB::table('consultations')->where('id', $consultationB->id)->delete();
        } catch (QueryException) {
            $queryDeleteRejected = true;
        }

        $this->assertTrue($modelDeleteRejected);
        $this->assertTrue($queryDeleteRejected);
        $this->assertDatabaseHas('consultations', ['id' => $consultationA->id]);
        $this->assertDatabaseHas('consultations', ['id' => $consultationB->id]);
        $this->assertSame($consultationA->id, $prescriptionA->refresh()->consultation_id);
        $this->assertSame($consultationB->id, $prescriptionB->refresh()->consultation_id);
    }

    public function test_unlinked_consultation_delete_keeps_authorized_behavior_and_ui_hides_only_linked_delete(): void
    {
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico');
        $administrator = $this->userForClinic($clinic, 'administrador');
        $doctor = $this->doctorFor($clinic, $owner);
        $linkedPatient = $this->patientFor($clinic, 'UILINKED');
        $linked = $this->consultationFor($linkedPatient, $doctor, '2026-07-22 21:00:00');
        $this->prescriptionFor($linkedPatient, $doctor, $linked);
        $unlinkedPatient = $this->patientFor($clinic, 'UIUNLINKED');
        $unlinked = $this->consultationFor($unlinkedPatient, $doctor, '2026-07-22 22:00:00');

        $this->actingAs($administrator)
            ->get(route('consultations.index'))
            ->assertOk()
            ->assertDontSee('<form method="POST" action="'.route('consultations.destroy', $linked).'"', escape: false)
            ->assertSee('<form method="POST" action="'.route('consultations.destroy', $unlinked).'"', escape: false);

        $this->actingAs($administrator)
            ->delete(route('consultations.destroy', $unlinked))
            ->assertRedirect(route('consultations.index'))
            ->assertSessionHas('success', 'Consulta eliminada correctamente.');

        $this->assertDatabaseMissing('consultations', ['id' => $unlinked->id]);
        $this->assertDatabaseHas('consultations', ['id' => $linked->id]);
        $audit = AuditLog::where('action', 'consultation.deleted')
            ->where('auditable_id', $unlinked->id)
            ->sole();
        $this->assertSame([], $audit->old_values);
        $this->assertSame('success', $audit->new_values['result']);
    }

    public function test_migration_down_restores_set_null_in_memory_and_finally_restores_restrict(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico');
        $doctor = $this->doctorFor($clinic, $owner);
        $patient = $this->patientFor($clinic, 'MIGRATIONDOWN');
        $consultation = $this->consultationFor($patient, $doctor, '2026-07-23 08:00:00');
        $prescription = $this->prescriptionFor($patient, $doctor, $consultation);
        $migration = $this->consultationForeignKeyMigration();

        try {
            $migration->down();
            $this->assertSame('set null', $this->consultationForeignKeyAction());
            DB::table('consultations')->where('id', $consultation->id)->delete();
            $this->assertDatabaseMissing('consultations', ['id' => $consultation->id]);
            $this->assertNull($prescription->refresh()->consultation_id);
        } finally {
            $migration->up();
        }

        $this->assertSame('restrict', $this->consultationForeignKeyAction());
    }

    public function test_migration_up_down_up_cycle_preserves_data_fk_and_indexes_without_duplicates(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico');
        $doctor = $this->doctorFor($clinic, $owner);
        $patient = $this->patientFor($clinic, 'MIGRATIONCYCLE');
        $consultation = $this->consultationFor($patient, $doctor, '2026-07-23 09:00:00');
        $prescription = $this->prescriptionFor($patient, $doctor, $consultation);
        $migration = $this->consultationForeignKeyMigration();
        $indexesBefore = $this->normalizedPrescriptionIndexes();

        try {
            $migration->up();
            $migration->down();
            $this->assertSame('set null', $this->consultationForeignKeyAction());
            $migration->up();
            $migration->up();

            $this->assertSame('restrict', $this->consultationForeignKeyAction());
            $this->assertCount(1, $this->consultationForeignKeys());
            $this->assertSame($indexesBefore, $this->normalizedPrescriptionIndexes());
            $this->assertDatabaseHas('consultations', ['id' => $consultation->id]);
            $this->assertDatabaseHas('prescriptions', [
                'id' => $prescription->id,
                'consultation_id' => $consultation->id,
            ]);
        } finally {
            $migration->up();
        }
    }

    public function test_direct_sql_identity_change_invalidates_signed_internal_integrity_and_can_be_restored(): void
    {
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico');
        $doctor = $this->doctorFor($clinic, $owner);
        $patient = $this->patientFor($clinic, 'HASHIDENTITY');
        $originalConsultation = $this->consultationFor($patient, $doctor, '2026-07-23 10:00:00');
        $otherConsultation = $this->consultationFor($patient, $doctor, '2026-07-23 11:00:00');
        $prescription = $this->prescriptionFor($patient, $doctor, $originalConsultation);
        $this->signPrescription($owner, $prescription);
        $prescription->refresh();
        $storedHash = (string) $prescription->signature_hash;

        try {
            DB::table('prescriptions')->where('id', $prescription->id)->update([
                'consultation_id' => $otherConsultation->id,
            ]);
            $prescription->refresh();
            $this->assertFalse(hash_equals($storedHash, $prescription->calculateSignatureHash()));
        } finally {
            DB::table('prescriptions')->where('id', $prescription->id)->update([
                'consultation_id' => $originalConsultation->id,
            ]);
        }

        $prescription->refresh();
        $this->assertSame($originalConsultation->id, $prescription->consultation_id);
        $this->assertTrue(hash_equals($storedHash, $prescription->calculateSignatureHash()));
    }

    public function test_cross_clinic_prescription_update_remains_uniform_not_found_and_preserves_state(): void
    {
        $clinic = $this->clinic();
        $actor = $this->userForClinic($clinic, 'medico');
        $this->doctorFor($clinic, $actor);
        $otherClinic = $this->clinic();
        $otherOwner = $this->userForClinic($otherClinic, 'medico');
        $otherDoctor = $this->doctorFor($otherClinic, $otherOwner);
        $otherPatient = $this->patientFor($otherClinic, 'CROSSCLINICUPDATE');
        $foreign = $this->prescriptionFor($otherPatient, $otherDoctor);
        $originalInstructions = $foreign->general_instructions;

        $this->actingAs($actor)
            ->putJson(route('prescriptions.update', $foreign), $this->updatePayload($foreign, [
                'general_instructions' => 'PHASE512_CROSS_CLINIC_MARKER',
            ]))
            ->assertNotFound()
            ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');

        $this->assertSame($originalInstructions, $foreign->refresh()->general_instructions);
    }

    public function test_cross_clinic_and_missing_consultation_delete_share_uniform_json_not_found(): void
    {
        $clinic = $this->clinic();
        $actor = $this->userForClinic($clinic, 'administrador');
        $otherClinic = $this->clinic();
        $otherOwner = $this->userForClinic($otherClinic, 'medico');
        $otherDoctor = $this->doctorFor($otherClinic, $otherOwner);
        $otherPatient = $this->patientFor($otherClinic, 'CROSSCLINICCONSULTATION');
        $foreign = $this->consultationFor($otherPatient, $otherDoctor, '2026-07-23 12:00:00');
        $missingId = (int) Consultation::max('id') + 1000;

        $foreignResponse = $this->actingAs($actor)
            ->deleteJson(route('consultations.destroy', $foreign));
        $missingResponse = $this->actingAs($actor)
            ->deleteJson(route('consultations.destroy', $missingId));

        $expected = [
            'message' => 'No se pudo completar la operación.',
            'code' => 'RESOURCE_NOT_FOUND',
        ];
        $foreignResponse->assertNotFound()->assertExactJson($expected);
        $missingResponse->assertNotFound()->assertExactJson($expected);
        $this->assertSame($foreignResponse->json(), $missingResponse->json());
        $this->assertDatabaseHas('consultations', ['id' => $foreign->id]);
    }

    public function test_inactive_user_cannot_open_or_store_a_prescription(): void
    {
        $clinic = $this->clinic();
        $inactiveAdministrator = $this->userForClinic($clinic, 'administrador');
        $inactiveAdministrator->forceFill(['status' => 'inactive'])->save();
        $doctorUser = $this->userForClinic($clinic, 'medico');
        $doctor = $this->doctorFor($clinic, $doctorUser);
        $patient = $this->patientFor($clinic, 'INACTIVECREATOR');

        $this->actingAs($inactiveAdministrator)
            ->get(route('prescriptions.create'))
            ->assertForbidden();

        $this->actingAs($inactiveAdministrator)
            ->post(route('prescriptions.store'), $this->storePayload($patient, $doctor, null))
            ->assertForbidden();

        $this->assertDatabaseCount('prescriptions', 0);
    }

    public function test_non_medical_non_administrator_role_cannot_delegate_prescription_doctor(): void
    {
        $clinic = $this->clinic();
        $customRole = Role::findOrCreate('prescription_creator_probe', 'web');
        $customRole->givePermissionTo('prescriptions.create');
        $actor = User::factory()->create([
            'clinic_id' => $clinic->id,
            'current_clinic_id' => $clinic->id,
            'status' => 'active',
        ]);
        $actor->assignRole($customRole);
        $doctorUser = $this->userForClinic($clinic, 'medico');
        $doctor = $this->doctorFor($clinic, $doctorUser);
        $patient = $this->patientFor($clinic, 'CUSTOMROLECREATOR');

        $this->actingAs($actor)
            ->get(route('prescriptions.create'))
            ->assertForbidden();

        $this->actingAs($actor)
            ->post(route('prescriptions.store'), $this->storePayload($patient, $doctor, null))
            ->assertForbidden();

        $this->assertDatabaseCount('prescriptions', 0);
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

    private function patientFor(Clinic $clinic, string $marker): Patient
    {
        return Patient::factory()->for($clinic)->create([
            'first_name' => $marker,
            'last_name' => 'Synthetic',
            'status' => 'active',
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function consultationFor(
        Patient $patient,
        Doctor $doctor,
        string $date,
        array $overrides = [],
    ): Consultation {
        return Consultation::factory()
            ->for($patient)
            ->for($doctor)
            ->create(array_merge([
                'consultation_date' => $date,
                'reason' => 'PHASE512_SYNTHETIC_REASON',
                'diagnosis' => 'PHASE512_SYNTHETIC_DIAGNOSIS',
            ], $overrides));
    }

    private function prescriptionFor(
        Patient $patient,
        Doctor $doctor,
        ?Consultation $consultation = null,
    ): Prescription {
        $prescription = Prescription::factory()
            ->for($patient)
            ->for($doctor)
            ->create([
                'consultation_id' => $consultation?->id,
                'prescription_date' => '2026-07-22',
                'general_instructions' => 'PHASE512_SYNTHETIC_INSTRUCTIONS',
                'status' => 'active',
            ]);
        PrescriptionItem::factory()->for($prescription)->create([
            'medication_name' => 'PHASE512_SYNTHETIC_ITEM',
        ]);

        return $prescription;
    }

    /** @param array<string, mixed> $overrides */
    private function storePayload(
        Patient $patient,
        Doctor $doctor,
        ?Consultation $consultation,
        array $overrides = [],
    ): array {
        return array_merge([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'consultation_id' => $consultation?->id,
            'prescription_date' => '2026-07-22',
            'general_instructions' => 'PHASE512_STORE_INSTRUCTIONS',
            'status' => 'active',
            'items' => [[
                'medication_name' => 'PHASE512_STORE_ITEM',
                'dosage' => 'synthetic',
                'frequency' => 'synthetic',
                'duration' => 'synthetic',
                'instructions' => 'synthetic',
            ]],
        ], $overrides);
    }

    /** @param array<string, mixed> $overrides */
    private function updatePayload(Prescription $prescription, array $overrides = []): array
    {
        return array_merge([
            'patient_id' => $prescription->patient_id,
            'doctor_id' => $prescription->doctor_id,
            'consultation_id' => $prescription->consultation_id,
            'prescription_date' => $prescription->prescription_date?->format('Y-m-d') ?? '2026-07-22',
            'general_instructions' => 'PHASE512_UPDATE_INSTRUCTIONS',
            'status' => 'active',
            'items' => [[
                'medication_name' => 'PHASE512_UPDATE_ITEM',
                'dosage' => 'synthetic',
                'frequency' => 'synthetic',
                'duration' => 'synthetic',
                'instructions' => 'synthetic',
            ]],
        ], $overrides);
    }

    private function signPrescription(User $owner, Prescription $prescription): void
    {
        $this->actingAs($owner)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('prescriptions.sign', $prescription))
            ->assertRedirect(route('prescriptions.show', $prescription));

        $this->assertTrue($prescription->refresh()->isSigned());
    }

    private function consultationForeignKeyMigration(): Migration
    {
        return require database_path('migrations/2026_07_22_000001_restrict_prescription_consultation_deletion.php');
    }

    /** @return array<int, array<string, mixed>> */
    private function consultationForeignKeys(): array
    {
        return array_values(array_filter(
            Schema::getForeignKeys('prescriptions'),
            static fn (array $foreignKey): bool => array_map(
                'strtolower',
                $foreignKey['columns'] ?? [],
            ) === ['consultation_id'],
        ));
    }

    private function consultationForeignKeyAction(): string
    {
        $foreignKeys = $this->consultationForeignKeys();
        $this->assertCount(1, $foreignKeys);

        return strtolower((string) ($foreignKeys[0]['on_delete'] ?? ''));
    }

    /** @return array<int, array<string, mixed>> */
    private function normalizedPrescriptionIndexes(): array
    {
        return collect(Schema::getIndexes('prescriptions'))
            ->map(static fn (array $index): array => [
                'name' => $index['name'],
                'columns' => $index['columns'],
                'unique' => $index['unique'],
                'primary' => $index['primary'],
            ])
            ->sortBy('name')
            ->values()
            ->all();
    }
}
