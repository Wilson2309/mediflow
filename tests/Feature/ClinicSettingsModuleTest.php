<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicSettingsModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_clinic_settings(): void
    {
        $this->get(route('settings.clinic.edit'))->assertRedirect(route('login', absolute: false));
        $this->put(route('settings.clinic.update'), [])->assertRedirect(route('login', absolute: false));
    }

    public function test_authenticated_user_with_clinic_can_view_settings(): void
    {
        $clinic = Clinic::factory()->create();

        $this->actingAs($this->userForClinic($clinic))
            ->get(route('settings.clinic.edit'))
            ->assertOk()
            ->assertSee('Configuración del consultorio');
    }

    public function test_settings_view_shows_own_clinic_data_and_real_counts(): void
    {
        $clinic = Clinic::factory()->create(['name' => 'Clínica Propia', 'ruc' => '0999999999001']);
        $user = $this->userForClinic($clinic);
        Patient::factory()->for($clinic)->create();
        Service::factory()->for($clinic)->create(['status' => 'active']);
        Service::factory()->for($clinic)->create(['status' => 'inactive']);
        Doctor::factory()->for($clinic)->create(['user_id' => null]);

        $this->actingAs($user)
            ->get(route('settings.clinic.edit'))
            ->assertOk()
            ->assertSee('Clínica Propia')
            ->assertSee('0999999999001')
            ->assertViewHas('clinic', fn ($viewClinic) => $viewClinic->users_count === 1
                && $viewClinic->patients_count === 1
                && $viewClinic->doctors_count === 1
                && $viewClinic->active_services_count === 1);
    }

    public function test_name_can_be_updated(): void
    {
        $clinic = Clinic::factory()->create();
        $this->updateClinic($clinic, ['name' => 'Centro Médico Actualizado'])
            ->assertRedirect(route('settings.clinic.edit'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('clinics', ['id' => $clinic->id, 'name' => 'Centro Médico Actualizado']);
    }

    public function test_ruc_can_be_updated(): void
    {
        $clinic = Clinic::factory()->create();
        $this->updateClinic($clinic, ['ruc' => '0991234567001']);
        $this->assertSame('0991234567001', $clinic->refresh()->ruc);
    }

    public function test_phone_can_be_updated(): void
    {
        $clinic = Clinic::factory()->create();
        $this->updateClinic($clinic, ['phone' => '042345678']);
        $this->assertSame('042345678', $clinic->refresh()->phone);
    }

    public function test_email_can_be_updated(): void
    {
        $clinic = Clinic::factory()->create();
        $this->updateClinic($clinic, ['email' => 'contacto@mediflow.test']);
        $this->assertSame('contacto@mediflow.test', $clinic->refresh()->email);
    }

    public function test_address_can_be_updated(): void
    {
        $clinic = Clinic::factory()->create();
        $this->updateClinic($clinic, ['address' => 'Av. Principal 123, Guayaquil']);
        $this->assertSame('Av. Principal 123, Guayaquil', $clinic->refresh()->address);
    }



    public function test_name_is_required(): void
    {
        $clinic = Clinic::factory()->create();

        $this->actingAs($this->userForClinic($clinic))
            ->from(route('settings.clinic.edit'))
            ->put(route('settings.clinic.update'), $this->validPayload($clinic, ['name' => '']))
            ->assertRedirect(route('settings.clinic.edit'))
            ->assertSessionHasErrors('name');
    }

    public function test_email_must_be_valid(): void
    {
        $clinic = Clinic::factory()->create();

        $this->actingAs($this->userForClinic($clinic))
            ->put(route('settings.clinic.update'), $this->validPayload($clinic, ['email' => 'correo-invalido']))
            ->assertSessionHasErrors('email');
    }



    public function test_submitted_clinic_id_cannot_change_target_clinic(): void
    {
        $clinic = Clinic::factory()->create(['name' => 'Clínica Propia']);
        $otherClinic = Clinic::factory()->create(['name' => 'Clínica Externa']);

        $this->actingAs($this->userForClinic($clinic))
            ->put(route('settings.clinic.update'), [
                ...$this->validPayload($clinic, ['name' => 'Clínica Propia Editada']),
                'clinic_id' => $otherClinic->id,
            ])
            ->assertRedirect(route('settings.clinic.edit'));

        $this->assertDatabaseHas('clinics', ['id' => $clinic->id, 'name' => 'Clínica Propia Editada']);
        $this->assertDatabaseHas('clinics', ['id' => $otherClinic->id, 'name' => 'Clínica Externa']);
    }

    public function test_user_without_clinic_cannot_access_settings(): void
    {
        $user = User::factory()->create(['clinic_id' => null]);

        $this->actingAs($user)->get(route('settings.clinic.edit'))->assertForbidden();
        $this->actingAs($user)->put(route('settings.clinic.update'), [])->assertForbidden();
    }

    public function test_other_clinic_data_is_not_shown(): void
    {
        $clinic = Clinic::factory()->create(['name' => 'Clínica Visible']);
        $otherClinic = Clinic::factory()->create(['name' => 'Clínica Secreta']);

        $this->actingAs($this->userForClinic($clinic))
            ->get(route('settings.clinic.edit'))
            ->assertOk()
            ->assertSee($clinic->name)
            ->assertDontSee($otherClinic->name);
    }

    public function test_sidebar_links_to_clinic_settings_and_uses_real_clinic_name(): void
    {
        $clinic = Clinic::factory()->create(['name' => 'Consultorio Dinámico']);

        $this->actingAs($this->userForClinic($clinic))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Configuración')
            ->assertSee('Consultorio Dinámico')
            ->assertSee(route('settings.clinic.edit'), escape: false);
    }

    public function test_updated_clinic_name_is_reflected_in_sidebar(): void
    {
        $clinic = Clinic::factory()->create(['name' => 'Nombre Anterior']);
        $user = $this->userForClinic($clinic);

        $this->actingAs($user)->put(route('settings.clinic.update'), $this->validPayload($clinic, ['name' => 'Nombre Nuevo']));
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Nombre Nuevo')->assertDontSee('Nombre Anterior');
    }

    private function userForClinic(Clinic $clinic): User
    {
        return User::factory()->create(['clinic_id' => $clinic->id]);
    }

    private function updateClinic(Clinic $clinic, array $overrides = [])
    {
        return $this->actingAs($this->userForClinic($clinic))
            ->put(route('settings.clinic.update'), $this->validPayload($clinic, $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function validPayload(Clinic $clinic, array $overrides = []): array
    {
        return array_merge([
            'name' => $clinic->name,
            'ruc' => $clinic->ruc,
            'phone' => $clinic->phone,
            'email' => $clinic->email,
            'address' => $clinic->address,
            'status' => $clinic->status,
        ], $overrides);
    }
}
