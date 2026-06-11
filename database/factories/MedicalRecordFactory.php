<?php

namespace Database\Factories;

use App\Models\MedicalRecord;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicalRecord>
 */
class MedicalRecordFactory extends Factory
{
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'personal_history' => fake()->paragraph(),
            'family_history' => fake()->paragraph(),
            'surgical_history' => fake()->sentence(),
            'current_medications' => fake()->sentence(),
            'chronic_diseases' => fake()->sentence(),
            'observations' => fake()->paragraph(),
        ];
    }
}
