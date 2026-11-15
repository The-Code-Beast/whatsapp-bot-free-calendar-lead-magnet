<?php

namespace Database\Factories;

use App\Models\WhatsAppConnection;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsAppConnection>
 */
class WhatsAppConnectionFactory extends Factory
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
            'waba_id' => fake()->numerify('##########'),
            'phone_number_id' => fake()->unique()->numerify('###############'),
            'display_phone_number' => '+15550001111',
            'verified_name' => fake()->company(),
            'status' => 'connected',
            'coexistence_enabled' => false,
            'access_token' => 'test-whatsapp-token',
            'connected_at' => now(),
        ];
    }
}
