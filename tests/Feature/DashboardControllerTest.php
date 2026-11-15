<?php

namespace Tests\Feature;

use App\Models\CalendarConnection;
use App\Models\Service;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dashboard_reports_workspace_and_calendar_setup_status(): void
    {
        $workspace = Workspace::factory()->create([
            'bot_instructions' => 'Only offer initial consultations.',
        ]);
        CalendarConnection::factory()->for($workspace)->create();
        Service::factory()->for($workspace)->create();

        $this->actingAs($workspace->owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Dashboard')
                ->where('setup.workspace', true)
                ->where('setup.calendar', true)
                ->where('setup.whatsapp', false)
                ->where('setup.assistant', true)
                ->where('stats.bookings_this_month', 0)
                ->where('stats.active_conversations', 0));
    }

    public function test_dashboard_reports_incomplete_setup_for_new_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Dashboard')
                ->where('setup.workspace', false)
                ->where('setup.calendar', false)
                ->where('setup.whatsapp', false)
                ->where('setup.assistant', false));
    }

    public function test_dashboard_requires_active_service_for_assistant_readiness(): void
    {
        $workspace = Workspace::factory()->create([
            'bot_instructions' => 'Only offer configured services.',
        ]);
        Service::factory()->for($workspace)->create(['is_active' => false]);

        $this->actingAs($workspace->owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('setup.assistant', false));
    }
}
