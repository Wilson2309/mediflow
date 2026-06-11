<?php

namespace Database\Seeders;

use App\Models\Clinic;
use Illuminate\Database\Seeder;

class ClinicSeeder extends Seeder
{
    public function run(): void
    {
        Clinic::updateOrCreate(
            ['ruc' => '0999999999001'],
            [
                'name' => 'Consultorio principal',
                'phone' => '0999999999',
                'email' => 'contacto@mediflow.com',
                'address' => 'Guayaquil, Ecuador',
                'status' => 'active',
            ]
        );
    }
}
