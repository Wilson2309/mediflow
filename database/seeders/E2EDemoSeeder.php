<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Service;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class E2EDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create or get the main clinic
        $clinic = Clinic::firstOrCreate(
            ['ruc' => '0999999999001'],
            [
                'name' => 'MediFlow Clinica E2E',
                'address' => 'Av. Principal E2E',
                'phone' => '0999999999',
                'email' => 'contacto@mediflow-e2e.com',
                'status' => 'active',
            ]
        );

        // 2. Admin User
        $admin = User::firstOrCreate(
            ['email' => 'admin@mediflow.com'],
            [
                'name' => 'Admin E2E',
                'password' => Hash::make('Admin123*'),
                'clinic_id' => $clinic->id,
            ]
        );
        $admin->syncRoles(['administrador']);

        // 3. Receptionist User
        $receptionist = User::firstOrCreate(
            ['email' => 'recepcionista@mediflow.com'],
            [
                'name' => 'Recepcion E2E',
                'password' => Hash::make('Password123*'),
                'clinic_id' => $clinic->id,
            ]
        );
        $receptionist->syncRoles(['recepcionista']);

        // 4. Cashier User
        $cashier = User::firstOrCreate(
            ['email' => 'caja@mediflow.com'],
            [
                'name' => 'Caja E2E',
                'password' => Hash::make('Password123*'),
                'clinic_id' => $clinic->id,
            ]
        );
        $cashier->syncRoles(['caja_finanzas']);

        // 5. Doctor User
        $doctorUser = User::firstOrCreate(
            ['email' => 'medico@mediflow.com'],
            [
                'name' => 'Dr. Medico E2E',
                'password' => Hash::make('Password123*'),
                'clinic_id' => $clinic->id,
            ]
        );
        $doctorUser->syncRoles(['medico']);

        // Associate a Specialty and Doctor record
        $specialty = Specialty::firstOrCreate(
            ['name' => 'Medicina General E2E'],
            ['description' => 'General testing specialty']
        );

        $doctor = Doctor::firstOrCreate(
            ['user_id' => $doctorUser->id],
            [
                'clinic_id' => $clinic->id,
                'specialty_id' => $specialty->id,
                'license_number' => 'MED-E2E-1234',
                'phone' => '0988888888',
                'consultation_fee' => 50.00,
                'status' => 'active',
            ]
        );

        $service = Service::firstOrCreate(
            ['clinic_id' => $clinic->id, 'name' => 'Consulta general E2E'],
            [
                'description' => 'Servicio base para pruebas E2E.',
                'price' => 35.00,
                'duration_minutes' => 30,
                'status' => 'active',
            ]
        );

        $doctor->services()->syncWithoutDetaching([$service->id]);
    }
}
