<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PrescriptionSignatureModuleTest extends TestCase
{
    use RefreshDatabase;

    private ?User $prescriptionDoctorUser = null;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->prescriptionDoctorUser = null;
        $this->withSession(['auth.password_confirmed_at' => time()]);
    }

    public function test_guest_cannot_sign_prescription(): void
    {
        $prescription = $this->prescriptionForClinic(Clinic::factory()->create());

        $this->post(route('prescriptions.sign', $prescription))
            ->assertRedirect(route('login', absolute: false));
    }

    public function test_user_without_permission_cannot_sign_prescription(): void
    {
        [$clinic, $user] = $this->clinicAndUser(role: 'recepcionista');
        $prescription = $this->prescriptionForClinic($clinic);

        $this->actingAs($user)
            ->post(route('prescriptions.sign', $prescription))
            ->assertForbidden();
    }

    public function test_user_with_permission_can_sign_prescription_from_own_clinic(): void
    {
        [$clinic, $user] = $this->clinicAndUser(['prescriptions.update', 'prescriptions.view']);
        $prescription = $this->prescriptionForClinic($clinic);

        $this->actingAs($user)
            ->post(route('prescriptions.sign', $prescription))
            ->assertRedirect(route('prescriptions.show', $prescription))
            ->assertSessionHas('success', 'Receta firmada electrónicamente correctamente.');

        $prescription->refresh();
        $this->assertTrue($prescription->isSigned());
        $this->assertNotNull($prescription->signed_at);
        $this->assertSame($user->id, $prescription->signed_by_user_id);
        $this->assertMatchesRegularExpression('/^RX-\d{8}-[A-Z0-9]{6}$/', $prescription->signature_verification_code);
        $this->assertNotNull($prescription->signature_hash);
    }

    public function test_user_cannot_sign_prescription_from_other_clinic(): void
    {
        [$clinic, $user] = $this->clinicAndUser(['prescriptions.update']);
        $otherPrescription = $this->prescriptionForClinic(Clinic::factory()->create());

        $this->actingAs($user)
            ->post(route('prescriptions.sign', $otherPrescription))
            ->assertNotFound();
    }

    public function test_cancelled_prescription_cannot_be_signed(): void
    {
        [$clinic, $user] = $this->clinicAndUser(['prescriptions.update']);
        $prescription = $this->prescriptionForClinic($clinic, status: 'cancelled');

        $this->actingAs($user)
            ->from(route('prescriptions.show', $prescription))
            ->post(route('prescriptions.sign', $prescription))
            ->assertRedirect(route('prescriptions.show', $prescription))
            ->assertSessionHas('error', 'No se puede firmar una receta cancelada.');

        $this->assertNull($prescription->refresh()->signed_at);
    }

    public function test_signing_stores_ip_and_user_agent(): void
    {
        [$clinic, $user] = $this->clinicAndUser(['prescriptions.update']);
        $prescription = $this->prescriptionForClinic($clinic);

        $this->actingAs($user)
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->withHeader('User-Agent', 'MediFlowTest/1.0')
            ->post(route('prescriptions.sign', $prescription))
            ->assertRedirect();

        $prescription->refresh();
        $this->assertSame('203.0.113.10', $prescription->signed_ip_address);
        $this->assertStringContainsString('MediFlowTest/1.0', $prescription->signed_user_agent);
    }

    public function test_signature_is_not_duplicated_when_prescription_is_already_signed(): void
    {
        [$clinic, $user] = $this->clinicAndUser(['prescriptions.update']);
        $prescription = $this->prescriptionForClinic($clinic);

        $this->actingAs($user)->post(route('prescriptions.sign', $prescription))->assertRedirect();
        $prescription->refresh();
        $originalCode = $prescription->signature_verification_code;
        $originalHash = $prescription->signature_hash;

        $this->actingAs($user)
            ->from(route('prescriptions.show', $prescription))
            ->post(route('prescriptions.sign', $prescription))
            ->assertRedirect(route('prescriptions.show', $prescription))
            ->assertSessionHas('error', 'La receta ya está firmada.');

        $prescription->refresh();
        $this->assertSame($originalCode, $prescription->signature_verification_code);
        $this->assertSame($originalHash, $prescription->signature_hash);
    }

    public function test_public_verification_page_works_with_valid_code(): void
    {
        [$clinic, $user] = $this->clinicAndUser(['prescriptions.update']);
        $prescription = $this->signedPrescription($clinic, $user);

        $this->get(route('prescriptions.verify', $prescription->signature_verification_code))
            ->assertOk()
            ->assertSee('Documento válido')
            ->assertSee($prescription->signature_verification_code)
            ->assertSee('REC-'.str_pad((string) $prescription->id, 6, '0', STR_PAD_LEFT));
    }

    public function test_public_verification_page_shows_not_found_for_invalid_code(): void
    {
        $this->get(route('prescriptions.verify', 'RX-20260620-XXXXXX'))
            ->assertOk()
            ->assertSee('Código no encontrado');
    }

    public function test_public_verification_detects_altered_content(): void
    {
        [$clinic, $user] = $this->clinicAndUser(['prescriptions.update']);
        $prescription = $this->signedPrescription($clinic, $user);
        $prescription->items()->first()->update(['dosage' => '1000 mg alterado']);

        $this->get(route('prescriptions.verify', $prescription->signature_verification_code))
            ->assertOk()
            ->assertSee('Documento alterado')
            ->assertSee('Advertencia: los datos de la receta no coinciden con la firma registrada.');
    }

    public function test_printable_pdf_view_shows_electronic_signature_block(): void
    {
        [$clinic, $user] = $this->clinicAndUser(['prescriptions.view', 'prescriptions.update']);
        $prescription = $this->signedPrescription($clinic, $user);

        $this->actingAs($user)
            ->get(route('prescriptions.print', $prescription))
            ->assertOk()
            ->assertSee('Firmado electrónicamente en MediFlow')
            ->assertSee($prescription->signature_verification_code)
            ->assertSee('Escanee este código para verificar la autenticidad del documento.')
            ->assertSee('data:image/svg+xml;base64', false);
    }

    public function test_printable_pdf_view_shows_unsigned_message_when_not_signed(): void
    {
        [$clinic, $user] = $this->clinicAndUser(['prescriptions.view']);
        $prescription = $this->prescriptionForClinic($clinic);

        $this->actingAs($user)
            ->get(route('prescriptions.print', $prescription))
            ->assertOk()
            ->assertSee('Documento no firmado electrónicamente.');
    }

    public function test_signed_prescription_pdf_can_be_downloaded(): void
    {
        [$clinic, $user] = $this->clinicAndUser(['prescriptions.view', 'prescriptions.update']);
        $prescription = $this->signedPrescription($clinic, $user);

        $response = $this->actingAs($user)->get(route('prescriptions.pdf', $prescription));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_show_displays_sign_button_when_prescription_can_be_signed(): void
    {
        [$clinic, $user] = $this->clinicAndUser(['prescriptions.view', 'prescriptions.update']);
        $prescription = $this->prescriptionForClinic($clinic);

        $this->actingAs($user)
            ->get(route('prescriptions.show', $prescription))
            ->assertOk()
            ->assertSee('Firmar receta')
            ->assertDontSee('Firmada electrónicamente');
    }

    public function test_show_displays_signed_badge_and_verification_link_when_already_signed(): void
    {
        [$clinic, $user] = $this->clinicAndUser(['prescriptions.view', 'prescriptions.update']);
        $prescription = $this->signedPrescription($clinic, $user);

        $this->actingAs($user)
            ->get(route('prescriptions.show', $prescription))
            ->assertOk()
            ->assertSee('Firmada electrónicamente')
            ->assertSee('Verificar firma')
            ->assertSee($prescription->signature_verification_code)
            ->assertDontSee('Firmar receta');
    }

    public function test_signed_prescription_cannot_be_edited(): void
    {
        [$clinic, $user] = $this->clinicAndUser(['prescriptions.update']);
        $prescription = $this->signedPrescription($clinic, $user);

        $this->actingAs($user)
            ->get(route('prescriptions.edit', $prescription))
            ->assertRedirect(route('prescriptions.show', $prescription))
            ->assertSessionHas('error', 'No se puede editar una receta firmada. Anule o cree una nueva receta.');
    }

    public function test_signed_prescription_cannot_be_updated(): void
    {
        [$clinic, $user] = $this->clinicAndUser(['prescriptions.update']);
        $prescription = $this->signedPrescription($clinic, $user);

        $this->actingAs($user)
            ->put(route('prescriptions.update', $prescription), $this->validPayload($prescription->patient, $prescription->doctor))
            ->assertRedirect(route('prescriptions.show', $prescription))
            ->assertSessionHas('error', 'No se puede editar una receta firmada. Anule o cree una nueva receta.');

        $this->assertNotSame('Instrucciones alteradas desde update', $prescription->refresh()->general_instructions);
    }

    private function clinicAndUser(array $permissions = [], string $role = 'medico'): array
    {
        $clinic = Clinic::factory()->create([
            'name' => 'Clínica Firma MediFlow',
            'ruc' => '0999999999001',
            'phone' => '0999999999',
            'email' => 'contacto@mediflow.test',
            'address' => 'Guayaquil, Ecuador',
            'status' => 'active',
        ]);
        $user = User::factory()->create(['clinic_id' => $clinic->id, 'name' => 'Usuario Firmante QA']);
        $user->assignRole($role);
        $this->prescriptionDoctorUser = $user;

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        if ($permissions !== []) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $user->givePermissionTo($permissions);
        }

        return [$clinic, $user];
    }

    private function signedPrescription(Clinic $clinic, User $user): Prescription
    {
        $prescription = $this->prescriptionForClinic($clinic);

        $this->actingAs($user)->post(route('prescriptions.sign', $prescription))->assertRedirect();

        return $prescription->refresh();
    }

    private function prescriptionForClinic(Clinic $clinic, string $status = 'active', string $medication = 'Paracetamol QA'): Prescription
    {
        $patient = Patient::factory()->for($clinic)->create([
            'first_name' => 'Wilson',
            'last_name' => 'Paciente',
            'email' => 'paciente@mediflow.test',
            'identification_number' => fake()->unique()->numerify('##########'),
            'allergies' => 'Alergia QA',
        ]);
        $doctorUser = $this->prescriptionDoctorUser
            && (int) $this->prescriptionDoctorUser->clinic_id === (int) $clinic->id
                ? $this->prescriptionDoctorUser
                : User::factory()->create(['clinic_id' => $clinic->id, 'name' => 'Dra. Firma QA']);

        if (! $doctorUser->roles()->exists()) {
            $doctorUser->assignRole('medico');
        }

        $doctor = Doctor::factory()
            ->for($clinic)
            ->for($doctorUser)
            ->for(Specialty::factory()->create(['name' => 'Medicina General']))
            ->create(['license_number' => fake()->unique()->bothify('LIC-####')]);
        $consultation = Consultation::factory()->for($patient)->for($doctor)->create([
            'reason' => 'Control médico QA',
            'diagnosis' => 'Diagnóstico QA',
            'consultation_date' => '2026-06-18 10:00:00',
        ]);
        $prescription = Prescription::factory()
            ->for($patient)
            ->for($doctor)
            ->for($consultation)
            ->create([
                'prescription_date' => '2026-06-18',
                'general_instructions' => 'Tomar abundante agua',
                'status' => $status,
            ]);

        PrescriptionItem::factory()->for($prescription)->create([
            'medication_name' => $medication,
            'dosage' => '500 mg',
            'frequency' => 'Cada 8 horas',
            'duration' => '3 días',
            'instructions' => 'Después de comer',
        ]);

        return $prescription;
    }

    private function validPayload(Patient $patient, Doctor $doctor): array
    {
        return [
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'consultation_id' => null,
            'prescription_date' => '2026-06-20',
            'general_instructions' => 'Instrucciones alteradas desde update',
            'status' => 'active',
            'items' => [
                [
                    'medication_name' => 'Ibuprofeno',
                    'dosage' => '400 mg',
                    'frequency' => 'Cada 12 horas',
                    'duration' => '2 días',
                    'instructions' => 'Si hay dolor',
                ],
            ],
        ];
    }
}
