<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\BusinessHour;
use App\Models\CalendarConnection;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AppointmentControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get(route('appointments.index'))->assertRedirect(route('login'));
    }

    public function test_index_only_displays_authenticated_users_appointments(): void
    {
        $ownWorkspace = Workspace::factory()->create();
        $otherAppointment = Appointment::factory()->create(['customer_name' => 'Other Customer']);
        Appointment::factory()->for($ownWorkspace)->create(['customer_name' => 'Own Customer']);

        $this->actingAs($ownWorkspace->owner)
            ->get(route('appointments.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Appointments/Index')
                ->has('appointments.data', 1)
                ->where('appointments.data.0.customer_name', 'Own Customer'));

        $this->assertNotSame($ownWorkspace->id, $otherAppointment->workspace_id);
    }

    public function test_valid_request_books_appointment_through_conflict_safe_flow(): void
    {
        $this->travelTo('2026-10-31 12:00:00');
        config()->set('cache.default', 'array');
        $workspace = Workspace::factory()->create([
            'timezone' => 'America/Los_Angeles',
            'appointment_duration_minutes' => 30,
            'buffer_minutes' => 0,
        ]);
        BusinessHour::factory()->for($workspace)->create(['day_of_week' => 1]);
        $connection = CalendarConnection::factory()->for($workspace)->create(['calendar_id' => 'primary@example.com']);
        Http::preventStrayRequests();
        Http::fake([
            'https://www.googleapis.com/calendar/v3/freeBusy' => Http::response([
                'calendars' => [$connection->calendar_id => ['busy' => []]],
            ]),
            'https://www.googleapis.com/calendar/v3/calendars/*/events' => Http::response(['id' => 'event-id']),
        ]);

        $this->actingAs($workspace->owner)
            ->post(route('appointments.store'), [
                'customer_name' => 'Taylor Client',
                'customer_phone' => '+15550001111',
                'customer_email' => 'taylor@example.com',
                'service' => 'Consultation',
                'starts_at' => '2026-11-02T11:00',
                'notes' => 'Created from dashboard',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Appointment booked and added to Google Calendar.');

        $this->assertDatabaseHas('appointments', [
            'workspace_id' => $workspace->id,
            'customer_name' => 'Taylor Client',
            'status' => 'confirmed',
            'google_event_id' => 'event-id',
        ]);
        $this->assertDatabaseHas('customers', [
            'workspace_id' => $workspace->id,
            'name' => 'Taylor Client',
            'phone' => '15550001111',
            'email' => 'taylor@example.com',
            'status' => 'booked',
            'source' => 'appointment',
        ]);
        Http::assertSentCount(2);
    }

    public function test_request_cannot_override_protected_appointment_fields(): void
    {
        $user = User::factory()->create();
        Workspace::factory()->for($user, 'owner')->create();

        $this->actingAs($user)
            ->post(route('appointments.store'), [
                'customer_name' => 'Taylor Client',
                'customer_phone' => '+15550001111',
                'starts_at' => '2026-11-02T11:00',
                'status' => 'cancelled',
                'workspace_id' => 999,
                'google_event_id' => 'injected-event',
            ])
            ->assertSessionHasErrors(['status', 'workspace_id', 'google_event_id']);

        $this->assertDatabaseCount('appointments', 0);
    }
}
