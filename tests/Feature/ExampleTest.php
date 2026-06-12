<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_public_landing_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Gestiona tu consultorio médico desde una sola plataforma')
            ->assertSee('Beneficios')
            ->assertSee('Módulos conectados')
            ->assertSee('Solicitar demo');
    }

    public function test_guest_sees_login_link_on_landing_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(route('login'), escape: false)
            ->assertSee('Iniciar sesión');
    }

    public function test_authenticated_user_sees_dashboard_link_on_landing_page(): void
    {
        $user = User::factory()->for(Clinic::factory())->create();
        $user->syncRoles(['administrador']);

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee(route('dashboard'), escape: false)
            ->assertSee('Ir al dashboard')
            ->assertDontSee('Iniciar sesión');
    }

    public function test_login_and_dashboard_routes_remain_available(): void
    {
        $this->get(route('login'))->assertOk();

        $user = User::factory()->for(Clinic::factory())->create();
        $user->syncRoles(['administrador']);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }
}
