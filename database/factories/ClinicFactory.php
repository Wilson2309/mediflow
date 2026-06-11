<?php

namespace Database\Factories;

use App\Models\Clinic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Clinic>
 */
class ClinicFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'ruc' => fake()->unique()->numerify('#############'),
            'phone' => fake()->numerify('09########'),
            'email' => fake()->unique()->companyEmail(),
            'address' => fake()->address(),
            'status' => 'active',
        ];
    }
}
