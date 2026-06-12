<?php

namespace Database\Factories;

use App\Models\DemoRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DemoRequest>
 */
class DemoRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'clinic_type' => fake()->randomElement(array_keys(DemoRequest::CLINIC_TYPES)),
            'doctors_count' => fake()->randomElement(array_keys(DemoRequest::DOCTORS_COUNTS)),
            'interest_module' => fake()->randomElement(array_keys(DemoRequest::INTEREST_MODULES)),
            'message' => fake()->sentence(),
            'status' => 'pending',
            'source' => 'landing',
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'contacted_at' => null,
            'notes' => null,
        ];
    }
}
