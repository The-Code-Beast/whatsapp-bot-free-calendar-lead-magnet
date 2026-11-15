<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\ServiceBusinessHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceBusinessHour>
 */
class ServiceBusinessHourFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'day_of_week' => fake()->numberBetween(0, 6),
            'opens_at' => '09:00',
            'closes_at' => '17:00',
            'is_enabled' => true,
        ];
    }
}
