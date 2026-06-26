<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\Doctor;
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
                'description' => 'Evaluacion medica general para diagnostico y orientacion inicial.',
                'price' => 25.00,
                'duration_minutes' => 30,
            ],
            [
                'name' => 'Consulta especializada',
                'description' => 'Atencion medica con especialista segun la necesidad clinica del paciente.',
                'price' => 40.00,
                'duration_minutes' => 45,
            ],
            [
                'name' => 'Control medico',
                'description' => 'Seguimiento de evolucion, tratamiento o condicion previamente diagnosticada.',
                'price' => 20.00,
                'duration_minutes' => 20,
            ],
            [
                'name' => 'Certificado medico',
                'description' => 'Emision de certificado medico segun evaluacion profesional.',
                'price' => 15.00,
                'duration_minutes' => 15,
            ],
            [
                'name' => 'Revision de resultados',
                'description' => 'Analisis de examenes, estudios o resultados complementarios.',
                'price' => 18.00,
                'duration_minutes' => 20,
            ],
        ];

        $doctorIds = Doctor::where('clinic_id', $clinic->id)->where('status', 'active')->pluck('id')->all();

        foreach ($services as $serviceData) {
            $service = Service::updateOrCreate(
                [
                    'clinic_id' => $clinic->id,
                    'name' => $serviceData['name'],
                ],
                [
                    'description' => $serviceData['description'],
                    'price' => $serviceData['price'],
                    'duration_minutes' => $serviceData['duration_minutes'],
                    'status' => 'active',
                ]
            );

            if ($doctorIds !== []) {
                $service->doctors()->syncWithoutDetaching($doctorIds);
            }
        }
    }
}
