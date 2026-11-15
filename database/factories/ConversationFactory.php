<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
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
            'whats_app_connection_id' => null,
            'customer_wa_id' => fake()->numerify('1555#######'),
            'customer_name' => fake()->name(),
            'status' => 'active',
            'booking_stage' => 'collecting',
            'booking_data' => null,
            'human_takeover' => false,
            'last_message_at' => now(),
        ];
    }
}
