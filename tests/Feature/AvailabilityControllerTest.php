<?php

namespace Tests\Feature;

use App\Models\AvailabilityBlock;
use App\Models\BusinessHour;
use App\Models\Service;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AvailabilityControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get(route('availability.edit'))->assertRedirect(route('login'));
    }

    public function test_user_without_workspace_sees_setup_prerequisite(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('availability.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Availability/Edit')
                ->where('workspace', null)
                ->has('blocks', 0)
                ->has('services', 0));
    }

    public function test_page_lists_only_the_authenticated_workspaces_availability(): void
    {
        $workspace = Workspace::factory()->create(['timezone' => 'America/Los_Angeles']);
        BusinessHour::factory()->for($workspace)->create(['day_of_week' => 1]);
        $service = Service::factory()->for($workspace)->create(['name' => 'Consultation']);
        AvailabilityBlock::factory()->for($workspace)->create([
            'starts_at' => '2026-11-10 18:00:00',
            'ends_at' => '2026-11-10 20:00:00',
            'reason' => 'Team meeting',
        ]);
        AvailabilityBlock::factory()->create(['reason' => 'Another tenant']);

        $this->actingAs($workspace->owner)
            ->get(route('availability.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('workspace.timezone', 'America/Los_Angeles')
                ->where('blocks.0.reason', 'Team meeting')
                ->where('blocks.0.starts_at', '2026-11-10T10:00')
                ->has('blocks', 1)
                ->where('services.0.id', $service->id));
    }

    public function test_user_can_update_weekly_hours_and_buffer(): void
    {
        $workspace = Workspace::factory()->create(['buffer_minutes' => 0]);

        $this->actingAs($workspace->owner)
            ->put(route('availability.update'), $this->hoursPayload())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Availability saved.');

        $this->assertSame(15, $workspace->fresh()->buffer_minutes);
        $this->assertDatabaseCount('business_hours', 7);
        $this->assertDatabaseHas('business_hours', [
            'workspace_id' => $workspace->id,
            'day_of_week' => 1,
            'opens_at' => '08:00',
            'closes_at' => '17:00',
            'is_enabled' => true,
        ]);
    }

    public function test_enabled_day_requires_valid_opening_and_closing_times(): void
    {
        $workspace = Workspace::factory()->create();
        $payload = $this->hoursPayload();
        $payload['business_hours'][1]['closes_at'] = '07:00';

        $this->actingAs($workspace->owner)
            ->put(route('availability.update'), $payload)
            ->assertSessionHasErrors(['business_hours.1.closes_at' => 'Closing time must be after opening time.']);

        $this->assertDatabaseCount('business_hours', 0);
    }

    public function test_user_can_add_local_blocked_time_that_is_stored_in_utc(): void
    {
        $workspace = Workspace::factory()->create(['timezone' => 'America/Los_Angeles']);

        $this->actingAs($workspace->owner)
            ->post(route('availability-blocks.store'), [
                'starts_at' => '2026-11-10T10:00',
                'ends_at' => '2026-11-10T12:00',
                'reason' => 'Team meeting',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Blocked time added.');

        $this->assertDatabaseHas('availability_blocks', [
            'workspace_id' => $workspace->id,
            'starts_at' => '2026-11-10 18:00:00',
            'ends_at' => '2026-11-10 20:00:00',
            'reason' => 'Team meeting',
        ]);
    }

    public function test_user_cannot_remove_another_workspaces_block(): void
    {
        $workspace = Workspace::factory()->create();
        $otherBlock = AvailabilityBlock::factory()->create();

        $this->actingAs($workspace->owner)
            ->delete(route('availability-blocks.destroy', $otherBlock))
            ->assertNotFound();

        $this->assertModelExists($otherBlock);
    }

    public function test_user_can_save_custom_hours_for_own_service(): void
    {
        $workspace = Workspace::factory()->create();
        $service = Service::factory()->for($workspace)->create();
        $payload = $this->hoursPayload();
        unset($payload['buffer_minutes']);
        $payload['uses_custom_hours'] = true;

        $this->actingAs($workspace->owner)
            ->put(route('service-availability.update', $service), $payload)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Service availability saved.');

        $this->assertTrue($service->fresh()->uses_custom_hours);
        $this->assertDatabaseHas('service_business_hours', [
            'service_id' => $service->id,
            'day_of_week' => 1,
            'opens_at' => '08:00',
            'closes_at' => '17:00',
        ]);
    }

    public function test_user_cannot_change_another_workspaces_service_schedule(): void
    {
        $workspace = Workspace::factory()->create();
        $otherService = Service::factory()->create();
        $payload = $this->hoursPayload();
        unset($payload['buffer_minutes']);
        $payload['uses_custom_hours'] = true;

        $this->actingAs($workspace->owner)
            ->put(route('service-availability.update', $otherService), $payload)
            ->assertNotFound();

        $this->assertFalse($otherService->fresh()->uses_custom_hours);
    }

    /** @return array<string, mixed> */
    private function hoursPayload(): array
    {
        return [
            'buffer_minutes' => 15,
            'business_hours' => collect(range(0, 6))->map(fn (int $day): array => [
                'day_of_week' => $day,
                'is_enabled' => $day > 0 && $day < 6,
                'opens_at' => $day > 0 && $day < 6 ? '08:00' : null,
                'closes_at' => $day > 0 && $day < 6 ? '17:00' : null,
            ])->all(),
        ];
    }
}
