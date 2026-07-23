<?php

namespace Tests\Feature;

use App\Http\Controllers\PrescriptionController;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Specialty;
use App\Models\User;
use App\Services\PrescriptionSignAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PrescriptionContextEnumerationRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_context_hides_foreign_resources_without_hiding_own_inactive_user_case(): void
    {
        $targetClinic = $this->clinic();
        $targetOwner = $this->userForClinic($targetClinic, 'medico');
        $foreignPrescription = $this->prescriptionFor($targetClinic, $targetOwner);
        $missingId = (int) Prescription::max('id') + 1000;

        $inactiveSourceClinic = $this->clinic();
        $inactiveUser = $this->userForClinic($inactiveSourceClinic, 'medico', 'inactive');
        $inactiveOwnPrescription = $this->prescriptionFor($inactiveSourceClinic, $inactiveUser);

        $detachedSourceClinic = $this->clinic();
        $detachedUser = $this->userForClinic($detachedSourceClinic, 'medico');
        $detachedOwnPrescription = $this->prescriptionFor($detachedSourceClinic, $detachedUser);
        $detachedUser->clinics()->detach($detachedSourceClinic->id);

        $closedSourceClinic = $this->clinic();
        $closedClinicUser = $this->userForClinic($closedSourceClinic, 'medico');
        $closedOwnPrescription = $this->prescriptionFor($closedSourceClinic, $closedClinicUser);
        $closedSourceClinic->update(['status' => 'inactive']);

        foreach ([$inactiveUser, $detachedUser, $closedClinicUser] as $actor) {
            $foreignWeb = $this->actingAs($actor)
                ->withSession($this->confirmedSession())
                ->post(route('prescriptions.sign', $foreignPrescription));
            $missingWeb = $this->actingAs($actor)
                ->withSession($this->confirmedSession())
                ->post(route('prescriptions.sign', $missingId));

            $foreignWeb->assertNotFound();
            $missingWeb->assertNotFound();
            $this->assertSame($foreignWeb->getContent(), $missingWeb->getContent());

            $foreign = $this->actingAs($actor)
                ->withSession($this->confirmedSession())
                ->postJson(route('prescriptions.sign', $foreignPrescription));
            $missing = $this->actingAs($actor)
                ->withSession($this->confirmedSession())
                ->postJson(route('prescriptions.sign', $missingId));

            $this->assertControlledError($foreign, 404, 'RESOURCE_NOT_FOUND');
            $this->assertControlledError($missing, 404, 'RESOURCE_NOT_FOUND');
            $this->assertSame($foreign->json(), $missing->json());
        }

        $this->assertControlledError(
            $this->actingAs($inactiveUser)
                ->withSession($this->confirmedSession())
                ->postJson(route('prescriptions.sign', $inactiveOwnPrescription)),
            403,
            'OPERATION_NOT_AUTHORIZED',
        );
        $this->assertControlledError(
            $this->actingAs($detachedUser)
                ->withSession($this->confirmedSession())
                ->postJson(route('prescriptions.sign', $detachedOwnPrescription)),
            403,
            'OPERATION_NOT_AUTHORIZED',
        );
        $this->assertControlledError(
            $this->actingAs($closedClinicUser)
                ->withSession($this->confirmedSession())
                ->postJson(route('prescriptions.sign', $closedOwnPrescription)),
            403,
            'OPERATION_NOT_AUTHORIZED',
        );

        $detachedUpdate = $this->actingAs($detachedUser)->putJson(
            route('prescriptions.update', $detachedOwnPrescription),
            $this->updatePayload($detachedOwnPrescription),
        );
        $this->assertControlledError($detachedUpdate, 403, 'OPERATION_NOT_AUTHORIZED');
    }

    public function test_sign_returns_controlled_404_when_resource_disappears_after_binding(): void
    {
        $clinic = $this->clinic();
        $owner = $this->userForClinic($clinic, 'medico');
        $prescription = $this->prescriptionFor($clinic, $owner);
        $request = Request::create(
            route('prescriptions.sign', $prescription),
            'POST',
            server: ['HTTP_ACCEPT' => 'application/json'],
        );
        $request->setUserResolver(static fn (): User => $owner);
        $this->actingAs($owner);

        $prescription->items()->delete();
        $prescription->delete();

        $response = app(PrescriptionController::class)->sign(
            $request,
            $prescription,
            app(PrescriptionSignAudit::class),
        );

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame([
            'message' => 'No se pudo completar la operación.',
            'code' => 'RESOURCE_NOT_FOUND',
        ], $response->getData(true));
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

    private function prescriptionFor(Clinic $clinic, User $owner): Prescription
    {
        $patient = Patient::factory()->for($clinic)->create();
        $doctor = Doctor::factory()
            ->for($clinic)
            ->for($owner)
            ->for(Specialty::factory()->create(['status' => 'active']))
            ->create(['status' => 'active']);
        $prescription = Prescription::factory()
            ->for($patient)
            ->for($doctor)
            ->create(['status' => 'active']);
        PrescriptionItem::factory()->for($prescription)->create();

        return $prescription;
    }

    private function updatePayload(Prescription $prescription): array
    {
        return [
            'patient_id' => $prescription->patient_id,
            'doctor_id' => $prescription->doctor_id,
            'consultation_id' => null,
            'prescription_date' => $prescription->prescription_date?->format('Y-m-d') ?? '2026-07-21',
            'general_instructions' => 'C02CONTEXTUPDATE',
            'status' => 'active',
            'items' => [[
                'medication_name' => 'C02CONTEXTITEM',
            ]],
        ];
    }

    private function confirmedSession(): array
    {
        return ['auth.password_confirmed_at' => time()];
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
