<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'meta_message_id' => 'wamid.'.fake()->uuid(),
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'type' => 'text',
            'body' => fake()->sentence(),
            'payload' => null,
            'status' => 'received',
            'sent_at' => now(),
            'read_at' => null,
        ];
    }
}
