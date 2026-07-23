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
use App\Policies\PrescriptionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PrescriptionSigningAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_prescription_policy_is_discovered(): void
    {
        $this->assertInstanceOf(
            PrescriptionPolicy::class,
            Gate::getPolicyFor(Prescription::class),
        );
    }

    public function test_administrator_cannot_sign_another_doctors_prescription(): void
    {
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico');
        $administrator = $this->userForClinic($clinic, 'administrador');
        $prescription = $this->prescriptionFor($clinic, $owner);

        $this->actingAs($administrator)
            ->withSession($this->confirmedSession())
            ->post(route('prescriptions.sign', $prescription))
            ->assertForbidden();

        $this->assertUnsigned($prescription);
    }

    public function test_another_doctor_cannot_sign_the_owners_prescription(): void
    {
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico');
        $otherDoctor = $this->userForClinic($clinic, 'medico');
        $prescription = $this->prescriptionFor($clinic, $owner);

        $this->actingAs($otherDoctor)
            ->withSession($this->confirmedSession())
            ->post(route('prescriptions.sign', $prescription))
            ->assertForbidden();

        $this->assertUnsigned($prescription);
    }

    public function test_active_owner_in_the_correct_clinic_can_sign_once(): void
    {
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico');
        $prescription = $this->prescriptionFor($clinic, $owner);

        $this->actingAs($owner)
            ->withSession($this->confirmedSession())
            ->post(route('prescriptions.sign', $prescription))
            ->assertRedirect(route('prescriptions.show', $prescription));

        $prescription->refresh();
        $this->assertTrue($prescription->isSigned());
        $this->assertSame($owner->id, $prescription->signed_by_user_id);
    }

    public function test_inactive_owner_is_denied(): void
    {
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico', status: 'inactive');
        $prescription = $this->prescriptionFor($clinic, $owner);

        $this->actingAs($owner)
            ->withSession($this->confirmedSession())
            ->post(route('prescriptions.sign', $prescription))
            ->assertForbidden();

        $this->assertUnsigned($prescription);
    }

    public function test_owner_without_sign_permission_is_denied(): void
    {
        $clinic = $this->clinic();
        Role::findOrCreate('medico_sin_firma', 'web');
        $owner = $this->userForClinic($clinic, 'medico_sin_firma');
        $prescription = $this->prescriptionFor($clinic, $owner);

        $this->actingAs($owner)
            ->withSession($this->confirmedSession())
            ->post(route('prescriptions.sign', $prescription))
            ->assertForbidden();

        $this->assertUnsigned($prescription);
        $this->assertSame(
            'missing_permission',
            AuditLog::where('action', 'prescriptions.sign')->sole()->new_values['reason_code'],
        );
    }

    public function test_payload_doctor_id_cannot_change_ownership_or_authorize_another_doctor(): void
    {
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico');
        $otherDoctorUser = $this->userForClinic($clinic, 'medico');
        $prescription = $this->prescriptionFor($clinic, $owner);
        $originalDoctorId = $prescription->doctor_id;
        $otherDoctor = $this->doctorFor($clinic, $otherDoctorUser);

        $this->actingAs($otherDoctorUser)
            ->withSession($this->confirmedSession())
            ->post(route('prescriptions.sign', $prescription), [
                'doctor_id' => $otherDoctor->id,
            ])
            ->assertForbidden();

        $prescription->refresh();
        $this->assertSame($originalDoctorId, $prescription->doctor_id);
        $this->assertUnsigned($prescription);
    }

    public function test_second_sign_attempt_keeps_all_signature_and_delivery_metadata_immutable(): void
    {
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico');
        $prescription = $this->prescriptionFor($clinic, $owner);

        $this->actingAs($owner)
            ->withSession($this->confirmedSession())
            ->post(route('prescriptions.sign', $prescription))
            ->assertRedirect();

        $fields = [
            'signed_at',
            'signed_by_user_id',
            'signature_verification_code',
            'signature_hash',
            'signed_ip_address',
            'signed_user_agent',
            'updated_at',
            'email_count',
            'print_count',
            'last_emailed_at',
            'last_printed_at',
        ];
        $prescription->refresh();
        $original = collect($fields)->mapWithKeys(
            fn (string $field): array => [$field => $prescription->getRawOriginal($field)],
        )->all();

        $this->actingAs($owner)
            ->withSession($this->confirmedSession())
            ->from(route('prescriptions.show', $prescription))
            ->post(route('prescriptions.sign', $prescription))
            ->assertRedirect(route('prescriptions.show', $prescription))
            ->assertSessionHas('error', 'La receta ya está firmada.');

        $prescription->refresh();

        foreach ($fields as $field) {
            $this->assertSame($original[$field], $prescription->getRawOriginal($field));
        }

        $logs = AuditLog::where('action', 'prescriptions.sign')
            ->where('auditable_id', $prescription->id)
            ->get();
        $this->assertCount(1, $logs->filter(fn (AuditLog $log): bool => $log->new_values['result'] === 'success'));
        $this->assertCount(1, $logs->filter(fn (AuditLog $log): bool => $log->new_values['result'] === 'denied'));
    }

    public function test_administrator_with_accidental_direct_sign_permission_is_still_denied(): void
    {
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico');
        $administrator = $this->userForClinic($clinic, 'administrador');
        $administrator->givePermissionTo('prescriptions.sign');
        $prescription = $this->prescriptionFor($clinic, $owner);

        $this->actingAs($administrator)
            ->withSession($this->confirmedSession())
            ->post(route('prescriptions.sign', $prescription))
            ->assertForbidden();

        $this->assertUnsigned($prescription);
        $this->assertSame(
            'not_owner',
            AuditLog::where('action', 'prescriptions.sign')->sole()->new_values['reason_code'],
        );
    }

    public function test_shared_doctor_can_sign_only_for_the_active_member_clinic(): void
    {
        $clinicA = $this->clinic();
        $clinicB = $this->clinic();
        $owner = $this->userForClinic($clinicA, 'medico');
        $owner->clinics()->syncWithoutDetaching([$clinicB->id]);
        $prescriptionA = $this->prescriptionFor($clinicA, $owner);
        $prescriptionB = $this->prescriptionFor($clinicB, $owner);

        $owner->forceFill(['current_clinic_id' => $clinicB->id])->save();

        $this->actingAs($owner)
            ->withSession($this->confirmedSession())
            ->post(route('prescriptions.sign', $prescriptionA))
            ->assertNotFound();
        $this->assertUnsigned($prescriptionA);

        $this->actingAs($owner)
            ->withSession($this->confirmedSession())
            ->post(route('prescriptions.sign', $prescriptionB))
            ->assertRedirect(route('prescriptions.show', $prescriptionB));

        $owner->forceFill(['current_clinic_id' => $clinicA->id])->save();

        $this->actingAs($owner)
            ->withSession($this->confirmedSession())
            ->post(route('prescriptions.sign', $prescriptionA))
            ->assertRedirect(route('prescriptions.show', $prescriptionA));

        $this->assertTrue($prescriptionA->refresh()->isSigned());
        $this->assertTrue($prescriptionB->refresh()->isSigned());
    }

    public function test_missing_membership_is_denied_before_signing(): void
    {
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico');
        $prescription = $this->prescriptionFor($clinic, $owner);
        $owner->clinics()->detach($clinic->id);

        $this->actingAs($owner)
            ->withSession($this->confirmedSession())
            ->post(route('prescriptions.sign', $prescription))
            ->assertForbidden();

        $this->assertUnsigned($prescription);
    }

    public function test_inactive_clinic_is_denied_before_signing(): void
    {
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico');
        $prescription = $this->prescriptionFor($clinic, $owner);
        $clinic->update(['status' => 'inactive']);

        $this->actingAs($owner)
            ->withSession($this->confirmedSession())
            ->post(route('prescriptions.sign', $prescription))
            ->assertForbidden();

        $this->assertUnsigned($prescription);
    }

    public function test_recent_password_confirmation_is_required(): void
    {
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico');
        $prescription = $this->prescriptionFor($clinic, $owner);

        $this->actingAs($owner)
            ->post(route('prescriptions.sign', $prescription))
            ->assertRedirect(route('password.confirm'));

        $this->assertUnsigned($prescription);
    }

    public function test_expired_password_confirmation_is_rejected(): void
    {
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico');
        $prescription = $this->prescriptionFor($clinic, $owner);

        $this->actingAs($owner)
            ->withSession(['auth.password_confirmed_at' => time() - 301])
            ->post(route('prescriptions.sign', $prescription))
            ->assertRedirect(route('password.confirm'));

        $this->assertUnsigned($prescription);
    }

    public function test_json_request_without_password_confirmation_returns_423(): void
    {
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico');
        $prescription = $this->prescriptionFor($clinic, $owner);

        $this->actingAs($owner)
            ->postJson(route('prescriptions.sign', $prescription))
            ->assertStatus(423);

        $this->assertUnsigned($prescription);
    }

    public function test_sign_button_is_owner_only_and_renders_a_double_submit_guard(): void
    {
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico');
        $otherDoctor = $this->userForClinic($clinic, 'medico');
        $administrator = $this->userForClinic($clinic, 'administrador');
        $prescription = $this->prescriptionFor($clinic, $owner);

        $this->actingAs($owner)
            ->get(route('prescriptions.show', $prescription))
            ->assertOk()
            ->assertSee('Firmar receta')
            ->assertSee('x-bind:disabled="submitting"', escape: false)
            ->assertSee('x-on:submit=', escape: false);

        $this->actingAs($otherDoctor)
            ->get(route('prescriptions.show', $prescription))
            ->assertOk()
            ->assertDontSee('Firmar receta');

        $this->actingAs($administrator)
            ->get(route('prescriptions.show', $prescription))
            ->assertOk()
            ->assertDontSee('Firmar receta')
            ->assertDontSee('Editar receta');
    }

    public function test_success_and_denial_audits_are_minimal_unique_and_contain_no_phi(): void
    {
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico');
        $otherDoctor = $this->userForClinic($clinic, 'medico');
        $prescription = $this->prescriptionFor($clinic, $owner);

        $this->actingAs($owner)
            ->withSession($this->confirmedSession())
            ->post(route('prescriptions.sign', $prescription))
            ->assertRedirect();

        $this->actingAs($otherDoctor)
            ->withSession($this->confirmedSession())
            ->post(route('prescriptions.sign', $prescription))
            ->assertForbidden();

        $logs = AuditLog::where('action', 'prescriptions.sign')
            ->where('auditable_id', $prescription->id)
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $logs);

        $success = $logs->first(fn (AuditLog $log): bool => $log->new_values['result'] === 'success');
        $denied = $logs->first(fn (AuditLog $log): bool => $log->new_values['result'] === 'denied');

        $this->assertNotNull($success);
        $this->assertNotNull($denied);
        $this->assertEqualsCanonicalizing([
            'request_id',
            'actor_user_id',
            'clinic_id',
            'prescription_id',
            'result',
            'signed_at',
        ], array_keys($success->new_values));
        $this->assertEqualsCanonicalizing([
            'request_id',
            'actor_user_id',
            'clinic_id',
            'prescription_id',
            'result',
            'reason_code',
        ], array_keys($denied->new_values));
        $this->assertSame('not_owner', $denied->new_values['reason_code']);
        $this->assertNotEmpty($success->new_values['request_id']);
        $this->assertNotEmpty($denied->new_values['request_id']);

        $serialized = strtolower((string) json_encode(
            $logs->map(fn (AuditLog $log): array => [
                'old' => $log->old_values,
                'new' => $log->new_values,
                'description' => $log->description,
            ])->all(),
        ));

        foreach ([
            'c02patientmarker',
            'c02.synthetic@example.invalid',
            'c02medicationmarker',
            'c02instructionmarker',
            'signature_verification_code',
            'signature_hash',
            'diagnosis',
            'medication_name',
            'general_instructions',
            'password',
            'token',
            'cookie',
        ] as $forbiddenValue) {
            $this->assertStringNotContainsString($forbiddenValue, $serialized);
        }
    }

    public function test_unauthorized_actor_is_denied_before_password_confirmation(): void
    {
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico');
        $administrator = $this->userForClinic($clinic, 'administrador');
        $prescription = $this->prescriptionFor($clinic, $owner);

        $this->actingAs($administrator)
            ->post(route('prescriptions.sign', $prescription))
            ->assertForbidden();

        $this->assertUnsigned($prescription);
        $this->assertSame(
            'missing_permission',
            AuditLog::where('action', 'prescriptions.sign')->sole()->new_values['reason_code'],
        );
    }

    public function test_inactive_doctor_record_is_denied(): void
    {
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico');
        $prescription = $this->prescriptionFor($clinic, $owner);
        $prescription->doctor->update(['status' => 'inactive']);

        $this->actingAs($owner)
            ->withSession($this->confirmedSession())
            ->post(route('prescriptions.sign', $prescription))
            ->assertForbidden();

        $this->assertUnsigned($prescription);
        $this->assertSame(
            'inactive_doctor',
            AuditLog::where('action', 'prescriptions.sign')->sole()->new_values['reason_code'],
        );
    }

    public function test_partial_signature_artifact_fails_closed_and_is_not_overwritten(): void
    {
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico');
        $prescription = $this->prescriptionFor($clinic, $owner);
        $partialArtifact = Str::random(24);
        $prescription->forceFill([
            'signature_verification_code' => $partialArtifact,
        ])->save();

        $this->actingAs($owner)
            ->withSession($this->confirmedSession())
            ->from(route('prescriptions.show', $prescription))
            ->post(route('prescriptions.sign', $prescription))
            ->assertRedirect(route('prescriptions.show', $prescription))
            ->assertSessionHas('error', 'La receta ya está firmada.');

        $prescription->refresh();
        $this->assertSame($partialArtifact, $prescription->signature_verification_code);
        $this->assertNull($prescription->signed_at);
        $this->assertNull($prescription->signed_by_user_id);
        $this->assertNull($prescription->signature_hash);
        $this->assertSame(
            'already_signed',
            AuditLog::where('action', 'prescriptions.sign')->sole()->new_values['reason_code'],
        );
    }

    public function test_password_confirmation_returns_to_show_then_allows_signing(): void
    {
        $clinic = $this->clinic();
        $syntheticPassword = Str::random(40);
        $owner = $this->userForClinic($clinic, 'medico');
        $owner->forceFill(['password' => Hash::make($syntheticPassword)])->save();
        $prescription = $this->prescriptionFor($clinic, $owner);

        $this->actingAs($owner)
            ->post(route('prescriptions.sign', $prescription))
            ->assertRedirect(route('password.confirm'))
            ->assertSessionHas('url.intended', route('prescriptions.show', $prescription));

        $this->post('/confirm-password', [
            'password' => $syntheticPassword,
        ])->assertRedirect(route('prescriptions.show', $prescription));

        $this->post(route('prescriptions.sign', $prescription))
            ->assertRedirect(route('prescriptions.show', $prescription));

        $this->assertTrue($prescription->refresh()->isSigned());
        $this->assertSame($owner->id, $prescription->signed_by_user_id);
    }

    public function test_signature_rolls_back_when_success_audit_cannot_be_persisted(): void
    {
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico');
        $prescription = $this->prescriptionFor($clinic, $owner);
        Schema::drop('audit_logs');

        $this->actingAs($owner)
            ->withSession($this->confirmedSession())
            ->from(route('prescriptions.show', $prescription))
            ->post(route('prescriptions.sign', $prescription))
            ->assertRedirect(route('prescriptions.show', $prescription))
            ->assertSessionHas('error', 'No se pudo completar la firma de la receta. Inténtelo nuevamente.');

        $this->assertUnsigned($prescription);
    }

    private function clinic(): Clinic
    {
        return Clinic::factory()->create(['status' => 'active']);
    }

    private function userForClinic(Clinic $clinic, string $role, string $status = 'active'): User
    {
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
            ->for(Specialty::factory()->create())
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
        $this->assertNull($prescription->signed_ip_address);
        $this->assertNull($prescription->signed_user_agent);
    }
}
