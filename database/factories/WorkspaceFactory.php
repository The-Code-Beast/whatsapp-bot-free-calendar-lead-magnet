<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workspace>
 */
class WorkspaceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'name' => fake()->company(),
            'timezone' => 'America/Los_Angeles',
            'appointment_duration_minutes' => 30,
            'buffer_minutes' => 10,
            'bot_instructions' => fake()->sentence(),
            'bot_tone' => 'friendly',
            'assistant_language' => 'auto',
            'bot_enabled' => false,
        ];
    }
}
