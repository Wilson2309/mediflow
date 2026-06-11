<?php

namespace Database\Seeders;

use App\Models\Specialty;
use Illuminate\Database\Seeder;

class SpecialtySeeder extends Seeder
{
    public function run(): void
    {
        $specialties = [
            'Medicina General' => 'Atención médica primaria y evaluación integral del paciente.',
            'Pediatría' => 'Atención médica para niños, niñas y adolescentes.',
            'Cardiología' => 'Diagnóstico y tratamiento de enfermedades cardiovasculares.',
            'Dermatología' => 'Evaluación y tratamiento de enfermedades de la piel.',
            'Ginecología' => 'Atención especializada en salud femenina.',
            'Odontología' => 'Prevención, diagnóstico y tratamiento dental.',
            'Psicología' => 'Evaluación y acompañamiento de salud mental.',
            'Traumatología' => 'Diagnóstico y tratamiento de lesiones musculoesqueléticas.',
        ];

        foreach ($specialties as $name => $description) {
            Specialty::updateOrCreate(
                ['name' => $name],
                [
                    'description' => $description,
                    'status' => 'active',
                ]
            );
        }
    }
}
