<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\DemoRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoRequestModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_displays_the_real_demo_request_form(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(route('demo-requests.store'), escape: false)
            ->assertSee('name="_token"', escape: false)
            ->assertSee('name="full_name"', escape: false)
            ->assertSee('name="clinic_type"', escape: false);
    }

    public function test_public_visitor_can_create_a_demo_request(): void
    {
        $this->post(route('demo-requests.store'), $this->validPayload([
            'full_name' => 'María López',
            'email' => 'maria@example.com',
        ]))
            ->assertRedirect(url('/').'#contacto')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('demo_requests', [
            'full_name' => 'María López',
            'email' => 'maria@example.com',
            'status' => 'pending',
            'source' => 'landing',
        ]);
    }

    public function test_name_and_email_are_required(): void
    {
        $this->post(route('demo-requests.store'), [])->assertSessionHasErrors(['full_name', 'email']);
    }

    public function test_email_must_be_valid(): void
    {
        $this->post(route('demo-requests.store'), $this->validPayload(['email' => 'correo-invalido']))
            ->assertSessionHasErrors('email');
    }

    public function test_optional_fields_can_be_omitted(): void
    {
        $this->post(route('demo-requests.store'), [
            'full_name' => 'Prospecto básico',
            'email' => 'prospecto@example.com',
        ])->assertRedirect();

        $this->assertDatabaseHas('demo_requests', [
            'email' => 'prospecto@example.com',
            'phone' => null,
            'clinic_type' => null,
            'interest_module' => null,
        ]);
    }

    public function test_invalid_select_values_are_rejected(): void
    {
        $this->post(route('demo-requests.store'), $this->validPayload([
            'clinic_type' => 'invalid',
            'interest_module' => 'unknown',
        ]))->assertSessionHasErrors(['clinic_type', 'interest_module']);
    }

    public function test_honeypot_simulates_success_without_saving(): void
    {
        $this->post(route('demo-requests.store'), $this->validPayload(['website' => 'https://spam.example']))
            ->assertRedirect(url('/').'#contacto')
            ->assertSessionHas('success');

        $this->assertDatabaseCount('demo_requests', 0);
    }

    public function test_request_metadata_is_recorded_by_the_server(): void
    {
        $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.9',
            'HTTP_USER_AGENT' => 'MediFlow Test Browser',
        ])->post(route('demo-requests.store'), $this->validPayload([
            'source' => 'forged-source',
            'status' => 'converted',
        ]));

        $this->assertDatabaseHas('demo_requests', [
            'source' => 'landing',
            'status' => 'pending',
            'ip_address' => '203.0.113.9',
            'user_agent' => 'MediFlow Test Browser',
        ]);
    }

    public function test_public_route_is_rate_limited(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->post(route('demo-requests.store'), $this->validPayload([
                'email' => "prospecto{$attempt}@example.com",
            ]))->assertRedirect();
        }

        $this->post(route('demo-requests.store'), $this->validPayload(['email' => 'blocked@example.com']))
            ->assertTooManyRequests();
    }

    public function test_guest_cannot_access_internal_demo_requests(): void
    {
        $this->get(route('demo-requests.index'))->assertRedirect(route('login', absolute: false));
    }

    public function test_administrator_can_view_the_request_list_and_detail(): void
    {
        $user = $this->userWithRole('administrador');
        $demoRequest = DemoRequest::factory()->create(['full_name' => 'Clínica Central']);

        $this->actingAs($user)->get(route('demo-requests.index'))->assertOk()->assertSee('Clínica Central');
        $this->actingAs($user)->get(route('demo-requests.show', $demoRequest))->assertOk()->assertSee($demoRequest->email);
    }

    public function test_receptionist_can_view_and_update_demo_requests(): void
    {
        $user = $this->userWithRole('recepcionista');
        $demoRequest = DemoRequest::factory()->create();

        $this->actingAs($user)->get(route('demo-requests.index'))->assertOk();
        $this->actingAs($user)->patch(route('demo-requests.update', $demoRequest), [
            'status' => 'contacted',
            'notes' => 'Contacto telefónico realizado.',
        ])->assertRedirect(route('demo-requests.show', $demoRequest));

        $this->assertDatabaseHas('demo_requests', ['id' => $demoRequest->id, 'status' => 'contacted']);
    }

    public function test_medico_and_cashier_cannot_access_demo_requests(): void
    {
        $demoRequest = DemoRequest::factory()->create();

        foreach (['medico', 'caja_finanzas'] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->get(route('demo-requests.index'))->assertForbidden();
            $this->actingAs($user)->patch(route('demo-requests.update', $demoRequest), ['status' => 'contacted'])->assertForbidden();
        }
    }

    public function test_changing_status_to_contacted_records_contacted_at(): void
    {
        $demoRequest = DemoRequest::factory()->create(['contacted_at' => null]);

        $this->actingAs($this->userWithRole('administrador'))
            ->patch(route('demo-requests.update', $demoRequest), ['status' => 'contacted', 'notes' => null])
            ->assertSessionHas('success');

        $this->assertNotNull($demoRequest->fresh()->contacted_at);
    }

    public function test_existing_contacted_at_is_preserved_on_later_status_changes(): void
    {
        $contactedAt = now()->subDay()->startOfSecond();
        $demoRequest = DemoRequest::factory()->create(['status' => 'contacted', 'contacted_at' => $contactedAt]);

        $this->actingAs($this->userWithRole('administrador'))
            ->patch(route('demo-requests.update', $demoRequest), ['status' => 'converted', 'notes' => null]);

        $this->assertTrue($demoRequest->fresh()->contacted_at->equalTo($contactedAt));
    }

    public function test_internal_notes_can_be_updated(): void
    {
        $demoRequest = DemoRequest::factory()->create();

        $this->actingAs($this->userWithRole('administrador'))
            ->patch(route('demo-requests.update', $demoRequest), [
                'status' => 'pending',
                'notes' => 'Llamar el próximo lunes.',
            ]);

        $this->assertDatabaseHas('demo_requests', ['id' => $demoRequest->id, 'notes' => 'Llamar el próximo lunes.']);
    }

    public function test_invalid_internal_status_is_rejected(): void
    {
        $demoRequest = DemoRequest::factory()->create();

        $this->actingAs($this->userWithRole('administrador'))
            ->patch(route('demo-requests.update', $demoRequest), ['status' => 'invalid'])
            ->assertSessionHasErrors('status');

        $this->assertSame('pending', $demoRequest->fresh()->status);
    }

    public function test_search_matches_name_email_and_phone(): void
    {
        $matching = DemoRequest::factory()->create(['full_name' => 'Centro Aurora', 'email' => 'aurora@example.com', 'phone' => '0991234567']);
        $other = DemoRequest::factory()->create(['full_name' => 'Centro Norte']);
        $user = $this->userWithRole('administrador');

        foreach (['Aurora', 'aurora@example.com', '0991234567'] as $search) {
            $this->actingAs($user)->get(route('demo-requests.index', ['search' => $search]))
                ->assertOk()->assertSee($matching->full_name)->assertDontSee($other->full_name);
        }
    }

    public function test_status_and_interest_module_filters_work(): void
    {
        $matching = DemoRequest::factory()->create(['full_name' => 'Coincide', 'status' => 'contacted', 'interest_module' => 'reports']);
        $other = DemoRequest::factory()->create(['full_name' => 'No coincide', 'status' => 'pending', 'interest_module' => 'appointments']);

        $this->actingAs($this->userWithRole('administrador'))
            ->get(route('demo-requests.index', ['status' => 'contacted', 'interest_module' => 'reports']))
            ->assertOk()->assertSee($matching->full_name)->assertDontSee($other->full_name);
    }

    public function test_sidebar_link_is_visible_only_with_permission(): void
    {
        $this->actingAs($this->userWithRole('recepcionista'))
            ->get(route('dashboard'))
            ->assertSee(route('demo-requests.index'), escape: false);

        $this->actingAs($this->userWithRole('medico'))
            ->get(route('dashboard'))
            ->assertDontSee(route('demo-requests.index'), escape: false);
    }

    public function test_dashboard_shows_pending_request_count_only_with_permission(): void
    {
        DemoRequest::factory()->count(3)->create();
        DemoRequest::factory()->create(['status' => 'contacted']);

        $this->actingAs($this->userWithRole('administrador'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Solicitudes de demo')
            ->assertSee('Prospectos pendientes');

        $this->actingAs($this->userWithRole('medico'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Solicitudes de demo');
    }

    /** @return array<string, string> */
    private function validPayload(array $overrides = []): array
    {
        return [
            'full_name' => 'Andrea Pérez',
            'email' => 'andrea@example.com',
            'phone' => '0999999999',
            'clinic_type' => 'private_office',
            'doctors_count' => '2-5',
            'interest_module' => 'complete_platform',
            'message' => 'Quiero conocer la plataforma.',
            ...$overrides,
        ];
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['clinic_id' => Clinic::factory()->create()->id]);
        $user->syncRoles([$role]);

        return $user;
    }
}
