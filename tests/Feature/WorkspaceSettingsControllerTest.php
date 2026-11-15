<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkspaceSettingsControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get(route('workspace.edit'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_workspace_setup(): void
    {
        config([
            'services.google_calendar.client_id' => null,
            'services.google_calendar.client_secret' => null,
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('workspace.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Settings/Workspace')
                ->where('workspace', null)
                ->has('timezones')
                ->where('googleConfigured', false));
    }

    public function test_valid_settings_create_workspace_and_business_hours(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('workspace.update'), $this->validPayload())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('workspaces', [
            'owner_id' => $user->id,
            'name' => 'Northstar Dental',
            'timezone' => 'America/Los_Angeles',
            'appointment_duration_minutes' => 45,
            'buffer_minutes' => 10,
            'assistant_language' => 'es',
        ]);
        $this->assertDatabaseCount('business_hours', 7);
        $this->assertDatabaseHas('business_hours', [
            'workspace_id' => $user->workspace->id,
            'day_of_week' => 1,
            'opens_at' => '09:00',
            'closes_at' => '17:00',
            'is_enabled' => true,
        ]);
    }

    public function test_enabled_day_rejects_closing_time_before_opening_time(): void
    {
        $user = User::factory()->create();
        $payload = $this->validPayload();
        $payload['business_hours'][1]['closes_at'] = '08:00';

        $this->actingAs($user)
            ->put(route('workspace.update'), $payload)
            ->assertSessionHasErrors([
                'business_hours.1.closes_at' => 'Closing time must be after opening time.',
            ]);

        $this->assertDatabaseCount('workspaces', 0);
    }

    public function test_update_only_changes_the_authenticated_users_workspace(): void
    {
        $firstWorkspace = Workspace::factory()->create(['name' => 'First Business']);
        $secondWorkspace = Workspace::factory()->create(['name' => 'Second Business']);
        $payload = $this->validPayload();
        $payload['name'] = 'Updated Second Business';

        $this->actingAs($secondWorkspace->owner)
            ->put(route('workspace.update'), $payload)
            ->assertSessionHasNoErrors();

        $this->assertSame('First Business', $firstWorkspace->fresh()->name);
        $this->assertSame('Updated Second Business', $secondWorkspace->fresh()->name);
    }

    /** @return array<string, mixed> */
    private function validPayload(): array
    {
        return [
            'name' => 'Northstar Dental',
            'timezone' => 'America/Los_Angeles',
            'appointment_duration_minutes' => 45,
            'buffer_minutes' => 10,
            'bot_tone' => 'friendly',
            'assistant_language' => 'es',
            'bot_instructions' => 'Ask which type of cleaning the patient needs.',
            'business_hours' => collect(range(0, 6))->map(fn (int $day): array => [
                'day_of_week' => $day,
                'is_enabled' => $day > 0 && $day < 6,
                'opens_at' => $day > 0 && $day < 6 ? '09:00' : null,
                'closes_at' => $day > 0 && $day < 6 ? '17:00' : null,
            ])->all(),
        ];
    }
}
