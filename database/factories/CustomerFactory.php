<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
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
            'name' => fake()->name(),
            'phone' => fake()->unique()->numerify('1555#######'),
            'email' => fake()->safeEmail(),
            'status' => 'new',
            'source' => 'whatsapp',
            'notes' => null,
            'last_contact_at' => now(),
        ];
    }
}
