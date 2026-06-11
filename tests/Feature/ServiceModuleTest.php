<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_services_index(): void
    {
        $this->get(route('services.index'))->assertRedirect(route('login', absolute: false));
    }

    public function test_authenticated_user_can_view_services_index(): void
    {
        $user = $this->userForClinic(Clinic::factory()->create());

        $this->actingAs($user)->get(route('services.index'))->assertOk()->assertSee('Servicios médicos');
    }

    public function test_only_services_from_authenticated_users_clinic_are_shown(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $ownService = Service::factory()->for($clinic)->create(['name' => 'Servicio visible']);
        $otherService = Service::factory()->for($otherClinic)->create(['name' => 'Servicio oculto']);

        $this->actingAs($this->userForClinic($clinic))
            ->get(route('services.index'))
            ->assertOk()
            ->assertSee($ownService->name)
            ->assertDontSee($otherService->name);
    }

    public function test_authenticated_user_can_create_service(): void
    {
        $clinic = Clinic::factory()->create();

        $this->actingAs($this->userForClinic($clinic))
            ->post(route('services.store'), $this->validPayload(['name' => 'Consulta general']))
            ->assertRedirect(route('services.index'))
            ->assertSessionHas('success', 'Servicio médico creado correctamente.');

        $this->assertDatabaseHas('services', ['clinic_id' => $clinic->id, 'name' => 'Consulta general']);
    }

    public function test_clinic_id_is_assigned_from_authenticated_user_when_creating_service(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();

        $this->actingAs($this->userForClinic($clinic))->post(route('services.store'), [
            ...$this->validPayload(['name' => 'Control médico']),
            'clinic_id' => $otherClinic->id,
        ]);

        $this->assertDatabaseHas('services', ['clinic_id' => $clinic->id, 'name' => 'Control médico']);
        $this->assertDatabaseMissing('services', ['clinic_id' => $otherClinic->id, 'name' => 'Control médico']);
    }

    public function test_authenticated_user_can_view_own_service_detail(): void
    {
        [$user, $service] = $this->userAndOwnService();

        $this->actingAs($user)->get(route('services.show', $service))->assertOk()->assertSee($service->name);
    }

    public function test_user_cannot_view_service_from_another_clinic(): void
    {
        [$user, $service] = $this->userAndOtherClinicService();

        $this->actingAs($user)->get(route('services.show', $service))->assertForbidden();
    }

    public function test_authenticated_user_can_open_edit_form_for_own_service(): void
    {
        [$user, $service] = $this->userAndOwnService();

        $this->actingAs($user)->get(route('services.edit', $service))->assertOk()->assertSee('Editar servicio')->assertSee($service->name);
    }

    public function test_user_cannot_edit_service_from_another_clinic(): void
    {
        [$user, $service] = $this->userAndOtherClinicService();

        $this->actingAs($user)->get(route('services.edit', $service))->assertForbidden();
    }

    public function test_authenticated_user_can_update_own_service(): void
    {
        [$user, $service] = $this->userAndOwnService();

        $this->actingAs($user)
            ->put(route('services.update', $service), $this->validPayload([
                'name' => 'Consulta especializada',
                'price' => 45.50,
                'duration_minutes' => 60,
                'status' => 'inactive',
            ]))
            ->assertRedirect(route('services.show', $service))
            ->assertSessionHas('success', 'Servicio médico actualizado correctamente.');

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'clinic_id' => $service->clinic_id,
            'name' => 'Consulta especializada',
            'price' => 45.50,
            'duration_minutes' => 60,
            'status' => 'inactive',
        ]);
    }

    public function test_user_cannot_update_service_from_another_clinic(): void
    {
        [$user, $service] = $this->userAndOtherClinicService();

        $this->actingAs($user)->put(route('services.update', $service), [])->assertForbidden();
    }

    public function test_authenticated_user_can_delete_own_service(): void
    {
        [$user, $service] = $this->userAndOwnService();

        $this->actingAs($user)
            ->delete(route('services.destroy', $service))
            ->assertRedirect(route('services.index'))
            ->assertSessionHas('success', 'Servicio médico eliminado correctamente.');

        $this->assertDatabaseMissing('services', ['id' => $service->id]);
    }

    public function test_user_cannot_delete_service_from_another_clinic(): void
    {
        [$user, $service] = $this->userAndOtherClinicService();

        $this->actingAs($user)->delete(route('services.destroy', $service))->assertForbidden();
        $this->assertDatabaseHas('services', ['id' => $service->id]);
    }

    public function test_required_fields_are_validated(): void
    {
        $user = $this->userForClinic(Clinic::factory()->create());

        $this->actingAs($user)
            ->from(route('services.create'))
            ->post(route('services.store'), [])
            ->assertRedirect(route('services.create'))
            ->assertSessionHasErrors(['name', 'price', 'duration_minutes', 'status']);
    }

    public function test_status_must_be_active_or_inactive(): void
    {
        $user = $this->userForClinic(Clinic::factory()->create());

        $this->actingAs($user)->post(route('services.store'), $this->validPayload(['status' => 'archived']))->assertSessionHasErrors('status');
    }

    public function test_price_must_be_within_valid_range(): void
    {
        $user = $this->userForClinic(Clinic::factory()->create());

        $this->actingAs($user)->post(route('services.store'), $this->validPayload(['price' => -0.01]))->assertSessionHasErrors('price');
        $this->actingAs($user)->post(route('services.store'), $this->validPayload(['price' => 100000000]))->assertSessionHasErrors('price');
    }

    public function test_duration_minutes_must_be_within_valid_range(): void
    {
        $user = $this->userForClinic(Clinic::factory()->create());

        $this->actingAs($user)->post(route('services.store'), $this->validPayload(['duration_minutes' => 0]))->assertSessionHasErrors('duration_minutes');
        $this->actingAs($user)->post(route('services.store'), $this->validPayload(['duration_minutes' => 1441]))->assertSessionHasErrors('duration_minutes');
    }

    public function test_search_by_service_name_works(): void
    {
        $clinic = Clinic::factory()->create();
        $matching = Service::factory()->for($clinic)->create(['name' => 'Certificado médico']);
        $other = Service::factory()->for($clinic)->create(['name' => 'Consulta general']);

        $this->actingAs($this->userForClinic($clinic))
            ->get(route('services.index', ['search' => 'Certificado']))
            ->assertOk()->assertSee($matching->name)->assertDontSee($other->name);
    }

    public function test_search_by_service_description_works(): void
    {
        $clinic = Clinic::factory()->create();
        $matching = Service::factory()->for($clinic)->create(['name' => 'Servicio A', 'description' => 'Evaluación cardiológica completa']);
        $other = Service::factory()->for($clinic)->create(['name' => 'Servicio B', 'description' => 'Control rutinario']);

        $this->actingAs($this->userForClinic($clinic))
            ->get(route('services.index', ['search' => 'cardiológica']))
            ->assertOk()->assertSee($matching->name)->assertDontSee($other->name);
    }

    public function test_filter_by_active_status_works(): void
    {
        $clinic = Clinic::factory()->create();
        $active = Service::factory()->for($clinic)->create(['name' => 'Servicio activo', 'status' => 'active']);
        $inactive = Service::factory()->for($clinic)->create(['name' => 'Servicio inactivo', 'status' => 'inactive']);

        $this->actingAs($this->userForClinic($clinic))
            ->get(route('services.index', ['status' => 'active']))
            ->assertOk()->assertSee($active->name)->assertDontSee($inactive->name);
    }

    public function test_filter_by_inactive_status_works(): void
    {
        $clinic = Clinic::factory()->create();
        $active = Service::factory()->for($clinic)->create(['name' => 'Servicio activo', 'status' => 'active']);
        $inactive = Service::factory()->for($clinic)->create(['name' => 'Servicio inactivo', 'status' => 'inactive']);

        $this->actingAs($this->userForClinic($clinic))
            ->get(route('services.index', ['status' => 'inactive']))
            ->assertOk()->assertSee($inactive->name)->assertDontSee($active->name);
    }

    private function userForClinic(Clinic $clinic): User
    {
        return User::factory()->create(['clinic_id' => $clinic->id]);
    }

    /** @return array{0: User, 1: Service} */
    private function userAndOwnService(): array
    {
        $clinic = Clinic::factory()->create();

        return [$this->userForClinic($clinic), Service::factory()->for($clinic)->create()];
    }

    /** @return array{0: User, 1: Service} */
    private function userAndOtherClinicService(): array
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();

        return [$this->userForClinic($clinic), Service::factory()->for($otherClinic)->create()];
    }

    /** @param array<string, mixed> $overrides */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Consulta de prueba',
            'description' => 'Descripción del servicio de prueba.',
            'price' => 25.00,
            'duration_minutes' => 30,
            'status' => 'active',
        ], $overrides);
    }
}
