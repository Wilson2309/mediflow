<?php

namespace Tests\Feature;

use App\Mail\PrescriptionMail;
use App\Models\Clinic;
use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PrescriptionDeliveryModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_guest_cannot_access_print_pdf_or_send_email(): void
    {
        $prescription = $this->prescriptionForClinic(Clinic::factory()->create());

        $this->get(route('prescriptions.print', $prescription))->assertRedirect(route('login', absolute: false));
        $this->get(route('prescriptions.pdf', $prescription))->assertRedirect(route('login', absolute: false));
        $this->post(route('prescriptions.send-email', $prescription))->assertRedirect(route('login', absolute: false));
    }

    public function test_user_with_prescriptions_view_can_see_printable_view(): void
    {
        [$clinic, $user] = $this->clinicAndUser(['prescriptions.view']);
        $prescription = $this->prescriptionForClinic($clinic);

        $this->actingAs($user)
            ->get(route('prescriptions.print', $prescription))
            ->assertOk()
            ->assertSee('Receta médica')
            ->assertSee('REC-'.str_pad((string) $prescription->id, 6, '0', STR_PAD_LEFT));
    }

    public function test_user_without_permission_receives_403(): void
    {
        [$clinic, $user] = $this->clinicAndUser();
        $prescription = $this->prescriptionForClinic($clinic);

        $this->actingAs($user)->get(route('prescriptions.print', $prescription))->assertForbidden();
    }

    public function test_user_cannot_access_prescription_from_other_clinic(): void
    {
        [$clinic, $user] = $this->clinicAndUser(['prescriptions.view', 'prescriptions.update']);
        $otherPrescription = $this->prescriptionForClinic(Clinic::factory()->create());

        $this->actingAs($user)->get(route('prescriptions.print', $otherPrescription))->assertNotFound();
        $this->actingAs($user)->get(route('prescriptions.pdf', $otherPrescription))->assertNotFound();
        $this->actingAs($user)->post(route('prescriptions.send-email', $otherPrescription))->assertNotFound();
    }

    public function test_printable_view_shows_clinic_patient_doctor_and_medications(): void
    {
        [$clinic, $user] = $this->clinicAndUser(['prescriptions.view']);
        $prescription = $this->prescriptionForClinic($clinic, medication: 'Amoxicilina QA');

        $this->actingAs($user)
            ->get(route('prescriptions.print', $prescription))
            ->assertOk()
            ->assertSee($clinic->name)
            ->assertSee($clinic->ruc)
            ->assertSee($prescription->patient->full_name)
            ->assertSee($prescription->patient->identification_number)
            ->assertSee($prescription->doctor->user->name)
            ->assertSee('Amoxicilina QA');
    }

    public function test_pdf_can_be_downloaded(): void
    {
        [$clinic, $user] = $this->clinicAndUser(['prescriptions.view']);
        $prescription = $this->prescriptionForClinic($clinic);

        $response = $this->actingAs($user)->get(route('prescriptions.pdf', $prescription));

        $response->assertOk();
        $response->assertHeader('content-disposition', 'attachment; filename=receta-medica-REC-'.str_pad((string) $prescription->id, 6, '0', STR_PAD_LEFT).'.pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_send_email_uses_patient_email_when_no_manual_email_is_sent(): void
    {
        Mail::fake();
        [$clinic, $user] = $this->clinicAndUser(['prescriptions.update']);
        $prescription = $this->prescriptionForClinic($clinic, patientEmail: 'paciente@mediflow.test');

        $this->actingAs($user)
            ->post(route('prescriptions.send-email', $prescription))
            ->assertRedirect()
            ->assertSessionHas('success', 'Receta enviada por correo correctamente.');

        Mail::assertSent(PrescriptionMail::class, fn (PrescriptionMail $mail) => $mail->hasTo('paciente@mediflow.test'));
    }

    public function test_send_email_allows_manual_recipient(): void
    {
        Mail::fake();
        [$clinic, $user] = $this->clinicAndUser(['prescriptions.update']);
        $prescription = $this->prescriptionForClinic($clinic);

        $this->actingAs($user)
            ->post(route('prescriptions.send-email', $prescription), ['email' => 'manual@mediflow.test'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Receta enviada por correo correctamente.');

        Mail::assertSent(PrescriptionMail::class, fn (PrescriptionMail $mail) => $mail->hasTo('manual@mediflow.test'));
    }

    public function test_send_email_requires_email_when_patient_has_no_email(): void
    {
        Mail::fake();
        [$clinic, $user] = $this->clinicAndUser(['prescriptions.update']);
        $prescription = $this->prescriptionForClinic($clinic, patientEmail: null);

        $this->actingAs($user)
            ->from(route('prescriptions.show', $prescription))
            ->post(route('prescriptions.send-email', $prescription), ['email' => null])
            ->assertRedirect(route('prescriptions.show', $prescription))
            ->assertSessionHasErrors('email');

        Mail::assertNothingSent();
    }

    public function test_successful_email_updates_tracking_fields(): void
    {
        Mail::fake();
        [$clinic, $user] = $this->clinicAndUser(['prescriptions.update']);
        $prescription = $this->prescriptionForClinic($clinic, patientEmail: 'paciente@mediflow.test');

        $this->actingAs($user)
            ->post(route('prescriptions.send-email', $prescription))
            ->assertRedirect()
            ->assertSessionHas('success', 'Receta enviada por correo correctamente.');

        $prescription->refresh();
        $this->assertNotNull($prescription->last_emailed_at);
        $this->assertSame('paciente@mediflow.test', $prescription->last_emailed_to);
        $this->assertSame(1, $prescription->email_count);
    }

    public function test_print_updates_tracking_fields(): void
    {
        [$clinic, $user] = $this->clinicAndUser(['prescriptions.view']);
        $prescription = $this->prescriptionForClinic($clinic);

        $this->actingAs($user)->get(route('prescriptions.print', $prescription))->assertOk();

        $prescription->refresh();
        $this->assertNotNull($prescription->last_printed_at);
        $this->assertSame(1, $prescription->print_count);
    }

    public function test_show_buttons_are_visible_for_user_with_permissions(): void
    {
        [$clinic, $user] = $this->clinicAndUser(['prescriptions.view', 'prescriptions.update']);
        $prescription = $this->prescriptionForClinic($clinic);

        $this->actingAs($user)
            ->get(route('prescriptions.show', $prescription))
            ->assertOk()
            ->assertSee('Imprimir receta')
            ->assertSee('Descargar PDF')
            ->assertSee('Enviar por correo');
    }

    public function test_send_email_controls_are_hidden_for_user_without_update_permission(): void
    {
        [$clinic, $user] = $this->clinicAndUser(['prescriptions.view']);
        $prescription = $this->prescriptionForClinic($clinic);

        $this->actingAs($user)
            ->get(route('prescriptions.show', $prescription))
            ->assertOk()
            ->assertSee('Imprimir receta')
            ->assertSee('Descargar PDF')
            ->assertDontSee('Enviar por correo');
    }

    private function clinicAndUser(array $permissions = []): array
    {
        $clinic = Clinic::factory()->create([
            'name' => 'Clinica QA MediFlow',
            'ruc' => '0999999999001',
            'phone' => '0999999999',
            'email' => 'contacto@mediflow.test',
            'address' => 'Guayaquil, Ecuador',
            'status' => 'active',
        ]);
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $user->assignRole('recepcionista');

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        if ($permissions !== []) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $user->givePermissionTo($permissions);
        }

        return [$clinic, $user];
    }

    private function prescriptionForClinic(Clinic $clinic, ?string $patientEmail = 'paciente@mediflow.test', string $medication = 'Paracetamol QA'): Prescription
    {
        $patient = Patient::factory()->for($clinic)->create([
            'first_name' => 'Paciente',
            'last_name' => 'Receta QA',
            'email' => $patientEmail,
            'identification_number' => fake()->unique()->numerify('##########'),
            'allergies' => 'Alergia QA',
        ]);
        $doctorUser = User::factory()->create(['clinic_id' => $clinic->id, 'name' => 'Dra. Receta QA']);
        $doctor = Doctor::factory()
            ->for($clinic)
            ->for($doctorUser)
            ->for(Specialty::factory()->create(['name' => 'Medicina General']))
            ->create(['license_number' => fake()->unique()->bothify('LIC-####')]);
        $consultation = Consultation::factory()->for($patient)->for($doctor)->create([
            'reason' => 'Control medico QA',
            'diagnosis' => 'Diagnostico QA',
            'consultation_date' => '2026-06-18 10:00:00',
        ]);
        $prescription = Prescription::factory()
            ->for($patient)
            ->for($doctor)
            ->for($consultation)
            ->create([
                'prescription_date' => '2026-06-18',
                'general_instructions' => 'Tomar abundante agua',
                'status' => 'active',
            ]);

        PrescriptionItem::factory()->for($prescription)->create([
            'medication_name' => $medication,
            'dosage' => '500 mg',
            'frequency' => 'Cada 8 horas',
            'duration' => '3 dias',
            'instructions' => 'Despues de comer',
        ]);

        return $prescription;
    }
}