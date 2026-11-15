<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
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
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'duration_minutes' => 30,
            'price_cents' => 7500,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
