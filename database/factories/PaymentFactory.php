<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'patient_id' => Patient::factory(),
            'appointment_id' => null,
            'service_id' => Service::factory(),
            'amount' => fake()->randomFloat(2, 10, 300),
            'payment_method' => fake()->randomElement(['cash', 'card', 'transfer', 'other']),
            'payment_status' => 'pending',
            'payment_date' => null,
            'notes' => fake()->sentence(),
        ];
    }

    public function forAppointment(Appointment $appointment): static
    {
        return $this->state(fn () => [
            'clinic_id' => $appointment->clinic_id,
            'patient_id' => $appointment->patient_id,
            'appointment_id' => $appointment->id,
            'service_id' => $appointment->service_id,
        ]);
    }
}
