<?php

namespace Database\Factories;

use App\Models\AvailabilityBlock;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AvailabilityBlock>
 */
class AvailabilityBlockFactory extends Factory
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
            'starts_at' => now()->addDay()->startOfDay(),
            'ends_at' => now()->addDay()->endOfDay(),
            'reason' => fake()->words(3, true),
        ];
    }
}
