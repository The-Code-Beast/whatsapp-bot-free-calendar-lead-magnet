<?php

namespace Database\Factories;

use App\Models\CalendarConnection;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalendarConnection>
 */
class CalendarConnectionFactory extends Factory
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
            'provider' => 'google',
            'provider_account_id' => fake()->safeEmail(),
            'email' => fake()->safeEmail(),
            'calendar_id' => 'primary',
            'calendar_name' => 'Primary calendar',
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'token_expires_at' => now()->addHour(),
            'status' => 'connected',
        ];
    }
}
