<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private function getSuperAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        return $user;
    }

    private function getRegularAdmin(): User
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $user->assignRole('administrador');
        return $user;
    }

    public function test_regular_admin_cannot_access_super_admin_routes(): void
    {
        $user = $this->getRegularAdmin();

        $this->actingAs($user)->get(route('super-admin.clinics.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_access_clinics_index(): void
    {
        $user = $this->getSuperAdmin();

        $this->actingAs($user)->get(route('super-admin.clinics.index'))
            ->assertOk()
            ->assertSee('Nueva Clínica');
    }

    public function test_super_admin_can_create_clinic_and_its_admin(): void
    {
        $user = $this->getSuperAdmin();

        $response = $this->actingAs($user)->post(route('super-admin.clinics.store'), [
            'name' => 'Clínica de Prueba',
            'ruc' => '1234567890',
            'phone' => '0999999999',
            'email' => 'contacto@clinica.com',
            'address' => 'Av. Principal',
            'status' => 'active',
            'admin_name' => 'Dr. Admin',
            'admin_email' => 'admin@clinica.com',
            'admin_password' => 'password',
            'admin_password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('super-admin.clinics.index'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('clinics', [
            'name' => 'Clínica de Prueba',
            'email' => 'contacto@clinica.com',
        ]);

        $clinic = Clinic::where('email', 'contacto@clinica.com')->first();

        $this->assertDatabaseHas('users', [
            'email' => 'admin@clinica.com',
            'name' => 'Dr. Admin',
            'clinic_id' => $clinic->id,
        ]);

        $admin = User::where('email', 'admin@clinica.com')->first();
        $this->assertTrue($admin->hasRole('administrador'));
    }

    public function test_super_admin_can_edit_clinic(): void
    {
        $user = $this->getSuperAdmin();
        $clinic = Clinic::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($user)->patch(route('super-admin.clinics.update', $clinic), [
            'name' => 'New Name',
            'status' => 'inactive',
        ]);

        $response->assertRedirect(route('super-admin.clinics.index'));
        
        $this->assertDatabaseHas('clinics', [
            'id' => $clinic->id,
            'name' => 'New Name',
            'status' => 'inactive',
        ]);
    }
}
