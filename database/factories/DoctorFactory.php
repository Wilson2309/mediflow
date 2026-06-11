<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Doctor>
 */
class DoctorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'user_id' => User::factory(),
            'specialty_id' => Specialty::factory(),
            'license_number' => fake()->unique()->bothify('MED-####??'),
            'phone' => fake()->numerify('09########'),
            'consultation_fee' => fake()->randomFloat(2, 20, 150),
            'status' => 'active',
        ];
    }
}
