<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\BusinessHour;
use App\Models\CalendarConnection;
use App\Models\Service;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AppointmentLifecycleTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_owner_can_reschedule_confirmed_appointment_and_google_event(): void
    {
        $this->travelTo('2026-10-31 12:00:00');
        [$workspace, $appointment] = $this->configuredAppointment();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.googleapis.com/calendar/v3/freeBusy' => Http::response(['calendars' => ['primary@example.com' => ['busy' => []]]]),
            'https://www.googleapis.com/calendar/v3/calendars/*/events/*' => Http::response(['id' => 'google-event-123']),
        ]);

        $this->actingAs($workspace->owner)
            ->put(route('appointments.reschedule', $appointment->public_id), ['starts_at' => '2026-11-02T11:00'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Appointment rescheduled.');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'starts_at' => '2026-11-02 19:00:00',
            'ends_at' => '2026-11-02 19:30:00',
            'status' => 'confirmed',
        ]);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && str_ends_with($request->url(), '/events/google-event-123')
            && $request['start']['dateTime'] === '2026-11-02T19:00:00+00:00');
    }

    public function test_unavailable_reschedule_keeps_original_time(): void
    {
        $this->travelTo('2026-10-31 12:00:00');
        [$workspace, $appointment] = $this->configuredAppointment();
        Appointment::factory()->for($workspace)->create([
            'starts_at' => '2026-11-02 19:00:00',
            'ends_at' => '2026-11-02 19:30:00',
        ]);
        Http::preventStrayRequests();

        $this->actingAs($workspace->owner)
            ->put(route('appointments.reschedule', $appointment->public_id), ['starts_at' => '2026-11-02T11:00'])
            ->assertSessionHasErrors('starts_at');

        $this->assertSame('2026-11-02 18:00:00', $appointment->fresh()->starts_at->format('Y-m-d H:i:s'));
        Http::assertNothingSent();
    }

    public function test_owner_can_cancel_appointment_and_google_event(): void
    {
        [$workspace, $appointment] = $this->configuredAppointment();
        Http::preventStrayRequests();
        Http::fake(['https://www.googleapis.com/calendar/v3/calendars/*/events/*' => Http::response([], 204)]);

        $this->actingAs($workspace->owner)
            ->delete(route('appointments.cancel', $appointment->public_id))
            ->assertSessionHas('success', 'Appointment cancelled.');

        $this->assertSame('cancelled', $appointment->fresh()->status);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
            && str_ends_with($request->url(), '/events/google-event-123'));
    }

    public function test_other_workspace_cannot_reschedule_or_cancel_appointment(): void
    {
        $workspace = Workspace::factory()->create();
        $otherAppointment = Appointment::factory()->create();

        $this->actingAs($workspace->owner)
            ->put(route('appointments.reschedule', $otherAppointment->public_id), ['starts_at' => '2026-11-02T11:00'])
            ->assertNotFound();
        $this->actingAs($workspace->owner)
            ->delete(route('appointments.cancel', $otherAppointment->public_id))
            ->assertNotFound();

        $this->assertSame('confirmed', $otherAppointment->fresh()->status);
    }

    /** @return array{Workspace, Appointment} */
    private function configuredAppointment(): array
    {
        $workspace = Workspace::factory()->create(['timezone' => 'America/Los_Angeles', 'buffer_minutes' => 0]);
        BusinessHour::factory()->for($workspace)->create(['day_of_week' => 1]);
        $connection = CalendarConnection::factory()->for($workspace)->create(['calendar_id' => 'primary@example.com']);
        $service = Service::factory()->for($workspace)->create(['duration_minutes' => 30]);
        $appointment = Appointment::factory()->for($workspace)->for($service, 'serviceModel')->create([
            'calendar_connection_id' => $connection->id,
            'starts_at' => '2026-11-02 18:00:00',
            'ends_at' => '2026-11-02 18:30:00',
            'timezone' => 'America/Los_Angeles',
            'google_event_id' => 'google-event-123',
            'status' => 'confirmed',
        ]);

        return [$workspace, $appointment];
    }
}
