<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Consultation>
 */
class ConsultationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'appointment_id' => null,
            'patient_id' => Patient::factory(),
            'doctor_id' => Doctor::factory(),
            'reason' => fake()->sentence(),
            'symptoms' => fake()->paragraph(),
            'diagnosis' => fake()->sentence(),
            'treatment' => fake()->paragraph(),
            'observations' => fake()->sentence(),
            'weight' => fake()->randomFloat(2, 45, 110),
            'height' => fake()->randomFloat(2, 1.45, 1.95),
            'temperature' => fake()->randomFloat(1, 36, 39),
            'blood_pressure' => fake()->randomElement(['120/80', '110/70', '130/85']),
            'heart_rate' => fake()->numberBetween(60, 110),
            'consultation_date' => fake()->dateTimeBetween('-1 month', 'now'),
        ];
    }

    public function forAppointment(Appointment $appointment): static
    {
        return $this->state(fn () => [
            'appointment_id' => $appointment->id,
            'patient_id' => $appointment->patient_id,
            'doctor_id' => $appointment->doctor_id,
        ]);
    }
}
