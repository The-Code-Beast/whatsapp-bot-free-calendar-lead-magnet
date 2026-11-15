<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AnalyticsControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get(route('analytics.index'))->assertRedirect(route('login'));
    }

    public function test_user_without_workspace_sees_empty_analytics_state(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('analytics.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Analytics/Index')
                ->where('hasWorkspace', false)
                ->where('range', 30)
                ->where('metrics', null)
                ->has('dailyVolume', 0));
    }

    public function test_analytics_reports_workspace_metrics_for_selected_range(): void
    {
        $this->travelTo('2026-11-04 12:00:00');
        $workspace = Workspace::factory()->create(['timezone' => 'UTC']);
        $consultation = Service::factory()->for($workspace)->create(['name' => 'Consultation', 'price_cents' => 10000]);
        $followUp = Service::factory()->for($workspace)->create(['name' => 'Follow-up', 'price_cents' => 5000]);
        Appointment::factory()->for($workspace)->for($consultation, 'serviceModel')->count(2)->create([
            'service' => 'Consultation',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->subDay()->addMinutes(30),
            'status' => 'confirmed',
        ]);
        Appointment::factory()->for($workspace)->for($followUp, 'serviceModel')->create([
            'service' => 'Follow-up',
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDays(2)->addMinutes(30),
            'status' => 'cancelled',
        ]);
        Appointment::factory()->for($workspace)->for($followUp, 'serviceModel')->create([
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDays(10)->addMinutes(30),
        ]);
        Appointment::factory()->for($workspace)->create([
            'starts_at' => now()->subDay(),
            'reminder_sent_at' => now()->subDay(),
            'followup_sent_at' => now()->subDay(),
        ]);
        Customer::factory()->for($workspace)->count(2)->create(['status' => 'lead']);
        Customer::factory()->for($workspace)->create(['status' => 'booked']);
        Customer::factory()->for($workspace)->create(['status' => 'completed']);
        Appointment::factory()->create(['starts_at' => now()->subDay()]);

        $this->actingAs($workspace->owner)
            ->get(route('analytics.index', ['range' => 7]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Analytics/Index')
                ->where('hasWorkspace', true)
                ->where('range', 7)
                ->where('timezone', 'UTC')
                ->where('metrics.appointments', 4)
                ->where('metrics.confirmed', 3)
                ->where('metrics.cancelled', 1)
                ->where('metrics.cancellation_rate', 25)
                ->where('metrics.lead_conversion_rate', 50)
                ->where('metrics.estimated_revenue_cents', 20000)
                ->where('metrics.reminders_sent', 1)
                ->where('metrics.followups_sent', 1)
                ->has('dailyVolume', 7)
                ->where('services.0.name', 'Consultation')
                ->where('services.0.appointments', 3)
                ->where('pipeline.1.status', 'lead')
                ->where('pipeline.1.count', 2));
    }

    public function test_invalid_range_falls_back_to_thirty_days(): void
    {
        $workspace = Workspace::factory()->create();

        $this->actingAs($workspace->owner)
            ->get(route('analytics.index', ['range' => 365]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('range', 30)
                ->has('dailyVolume', 30));
    }
}
