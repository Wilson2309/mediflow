<?php

namespace Database\Factories;

use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Prescription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prescription>
 */
class PrescriptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'doctor_id' => Doctor::factory(),
            'consultation_id' => null,
            'prescription_date' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'general_instructions' => fake()->sentence(),
            'status' => 'active',
        ];
    }

    public function forConsultation(Consultation $consultation): static
    {
        return $this->state(fn () => [
            'consultation_id' => $consultation->id,
            'patient_id' => $consultation->patient_id,
            'doctor_id' => $consultation->doctor_id,
        ]);
    }
}
