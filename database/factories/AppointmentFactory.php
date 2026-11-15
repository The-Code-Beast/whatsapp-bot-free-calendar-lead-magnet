<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'calendar_connection_id' => null,
            'customer_name' => fake()->name(),
            'customer_phone' => fake()->e164PhoneNumber(),
            'customer_email' => fake()->safeEmail(),
            'service' => 'Consultation',
            'starts_at' => now()->addDay()->setTime(10, 0),
            'ends_at' => now()->addDay()->setTime(10, 30),
            'timezone' => 'America/Los_Angeles',
            'status' => 'confirmed',
            'google_event_id' => 'booking'.fake()->unique()->regexify('[a-f0-9]{24}'),
            'idempotency_key' => fake()->uuid(),
            'notes' => fake()->sentence(),
        ];
    }
}
