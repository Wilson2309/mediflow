<?php

namespace Database\Factories;

use App\Models\Prescription;
use App\Models\PrescriptionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrescriptionItem>
 */
class PrescriptionItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'prescription_id' => Prescription::factory(),
            'medication_name' => fake()->randomElement(['Paracetamol', 'Ibuprofeno', 'Amoxicilina']),
            'dosage' => fake()->randomElement(['500 mg', '250 mg', '1 tableta']),
            'frequency' => fake()->randomElement(['Cada 8 horas', 'Cada 12 horas', 'Una vez al día']),
            'duration' => fake()->randomElement(['3 días', '5 días', '7 días']),
            'instructions' => fake()->sentence(),
        ];
    }
}
