<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AutomationSettingsControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get(route('automations.edit'))->assertRedirect(route('login'));
    }

    public function test_user_without_workspace_sees_setup_prerequisite(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('automations.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Automations/Edit')
                ->where('settings', null)
                ->where('stats.upcoming', 0));
    }

    public function test_page_shows_only_workspace_automation_statistics(): void
    {
        $workspace = Workspace::factory()->create();
        Appointment::factory()->for($workspace)->create([
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addMinutes(30),
            'reminder_sent_at' => now(),
        ]);
        Appointment::factory()->create(['reminder_sent_at' => now()]);

        $this->actingAs($workspace->owner)
            ->get(route('automations.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('settings.reminder_enabled', true)
                ->where('stats.upcoming', 1)
                ->where('stats.reminders_sent', 1)
                ->where('stats.followups_sent', 0));
    }

    public function test_owner_can_update_automation_settings(): void
    {
        $workspace = Workspace::factory()->create();

        $this->actingAs($workspace->owner)
            ->put(route('automations.update'), $this->validPayload())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Automation settings saved.');

        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
            'reminder_enabled' => true,
            'reminder_hours_before' => 12,
            'reminder_message' => 'Reminder for {name} at {time}',
            'followup_enabled' => false,
            'followup_hours_after' => 4,
            'followup_message' => 'Thanks, {name}',
        ]);
    }

    public function test_request_rejects_invalid_timing_and_protected_workspace_id(): void
    {
        $workspace = Workspace::factory()->create();
        $payload = $this->validPayload();
        $payload['reminder_hours_before'] = 5;
        $payload['followup_hours_after'] = 3;
        $payload['workspace_id'] = 999;

        $this->actingAs($workspace->owner)
            ->put(route('automations.update'), $payload)
            ->assertSessionHasErrors(['reminder_hours_before', 'followup_hours_after', 'workspace_id']);

        $this->assertSame(24, $workspace->fresh()->reminder_hours_before);
    }

    /** @return array<string, mixed> */
    private function validPayload(): array
    {
        return [
            'reminder_enabled' => true,
            'reminder_hours_before' => 12,
            'reminder_message' => 'Reminder for {name} at {time}',
            'followup_enabled' => false,
            'followup_hours_after' => 4,
            'followup_message' => 'Thanks, {name}',
        ];
    }
}
