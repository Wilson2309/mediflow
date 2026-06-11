<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $clinic = Clinic::where('ruc', '0999999999001')->firstOrFail();

        $services = [
            [
                'name' => 'Consulta general',
                'description' => 'Evaluación médica general para diagnóstico y orientación inicial.',
                'price' => 25.00,
                'duration_minutes' => 30,
            ],
            [
                'name' => 'Consulta especializada',
                'description' => 'Atención médica con especialista según la necesidad clínica del paciente.',
                'price' => 40.00,
                'duration_minutes' => 45,
            ],
            [
                'name' => 'Control médico',
                'description' => 'Seguimiento de evolución, tratamiento o condición previamente diagnosticada.',
                'price' => 20.00,
                'duration_minutes' => 20,
            ],
            [
                'name' => 'Certificado médico',
                'description' => 'Emisión de certificado médico según evaluación profesional.',
                'price' => 15.00,
                'duration_minutes' => 15,
            ],
            [
                'name' => 'Revisión de resultados',
                'description' => 'Análisis de exámenes, estudios o resultados complementarios.',
                'price' => 18.00,
                'duration_minutes' => 20,
            ],
        ];

        foreach ($services as $service) {
            Service::updateOrCreate(
                [
                    'clinic_id' => $clinic->id,
                    'name' => $service['name'],
                ],
                [
                    'description' => $service['description'],
                    'price' => $service['price'],
                    'duration_minutes' => $service['duration_minutes'],
                    'status' => 'active',
                ]
            );
        }
    }
}
