<?php

namespace Tests\Feature\Actions;

use App\Actions\CreateAppointment;
use App\Exceptions\SlotUnavailableException;
use App\Models\Appointment;
use App\Models\AvailabilityBlock;
use App\Models\BusinessHour;
use App\Models\CalendarConnection;
use App\Models\Service;
use App\Models\ServiceBusinessHour;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CreateAppointmentTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_available_slot_creates_confirmed_local_and_google_appointment(): void
    {
        $this->travelTo('2026-10-31 12:00:00');
        [$workspace] = $this->configuredWorkspace();
        $this->fakeAvailableGoogleCalendar();

        $appointment = app(CreateAppointment::class)->handle($workspace, $this->appointmentData(), 'message-123');

        $this->assertSame('confirmed', $appointment->status);
        $this->assertSame('google-event-123', $appointment->google_event_id);
        $this->assertSame('2026-11-02 10:00', $appointment->starts_at->setTimezone($workspace->timezone)->format('Y-m-d H:i'));
        $this->assertDatabaseHas('appointments', [
            'workspace_id' => $workspace->id,
            'idempotency_key' => 'message-123',
            'status' => 'confirmed',
        ]);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://www.googleapis.com/calendar/v3/freeBusy'
            && $request['timeMin'] === '2026-11-02T09:50:00-08:00'
            && $request['timeMax'] === '2026-11-02T10:40:00-08:00');
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/events')
            && $request['id'] === 'booking'.hash('sha256', 'message-123'));
    }

    public function test_same_idempotency_key_returns_existing_appointment_without_duplicate_requests(): void
    {
        $this->travelTo('2026-10-31 12:00:00');
        [$workspace] = $this->configuredWorkspace();
        $this->fakeAvailableGoogleCalendar();
        $action = app(CreateAppointment::class);

        $first = $action->handle($workspace, $this->appointmentData(), 'same-message');
        $second = $action->handle($workspace, $this->appointmentData(), 'same-message');

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('appointments', 1);
        Http::assertSentCount(2);
    }

    public function test_google_busy_period_rejects_slot_without_creating_event(): void
    {
        $this->travelTo('2026-10-31 12:00:00');
        [$workspace, $connection] = $this->configuredWorkspace();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.googleapis.com/calendar/v3/freeBusy' => Http::response([
                'calendars' => [
                    $connection->calendar_id => ['busy' => [['start' => '2026-11-02T10:00:00-08:00', 'end' => '2026-11-02T10:30:00-08:00']]],
                ],
            ]),
        ]);

        try {
            app(CreateAppointment::class)->handle($workspace, $this->appointmentData(), 'busy-message');
            $this->fail('The busy slot should have been rejected.');
        } catch (SlotUnavailableException $exception) {
            $this->assertSame('That time is no longer available. Please choose another time.', $exception->getMessage());
        }

        $this->assertDatabaseCount('appointments', 0);
        Http::assertSentCount(1);
    }

    public function test_local_conflict_rejects_slot_before_google_request(): void
    {
        $this->travelTo('2026-10-31 12:00:00');
        [$workspace, $connection] = $this->configuredWorkspace();
        Appointment::factory()->for($workspace)->create([
            'calendar_connection_id' => $connection->id,
            'starts_at' => '2026-11-02 18:00:00',
            'ends_at' => '2026-11-02 18:30:00',
        ]);
        Http::preventStrayRequests();

        $this->expectException(SlotUnavailableException::class);

        try {
            app(CreateAppointment::class)->handle($workspace, $this->appointmentData(), 'conflicting-message');
        } finally {
            $this->assertDatabaseCount('appointments', 1);
            Http::assertNothingSent();
        }
    }

    public function test_google_failure_removes_pending_local_appointment(): void
    {
        $this->travelTo('2026-10-31 12:00:00');
        [$workspace, $connection] = $this->configuredWorkspace();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.googleapis.com/calendar/v3/freeBusy' => Http::response([
                'calendars' => [$connection->calendar_id => ['busy' => []]],
            ]),
            'https://www.googleapis.com/calendar/v3/calendars/*/events' => Http::response(['error' => 'unavailable'], 503),
        ]);

        $this->expectException(RequestException::class);

        try {
            app(CreateAppointment::class)->handle($workspace, $this->appointmentData(), 'failed-message');
        } finally {
            $this->assertDatabaseCount('appointments', 0);
            Http::assertSentCount(2);
        }
    }

    public function test_blocked_time_is_rejected_before_google_request(): void
    {
        $this->travelTo('2026-10-31 12:00:00');
        [$workspace] = $this->configuredWorkspace();
        AvailabilityBlock::factory()->for($workspace)->create([
            'starts_at' => '2026-11-02 17:30:00',
            'ends_at' => '2026-11-02 19:00:00',
        ]);
        Http::preventStrayRequests();

        try {
            app(CreateAppointment::class)->handle($workspace, $this->appointmentData(), 'blocked-message');
            $this->fail('The blocked slot should have been rejected.');
        } catch (SlotUnavailableException $exception) {
            $this->assertSame('That time has been blocked off. Please choose another time.', $exception->getMessage());
        }

        $this->assertDatabaseCount('appointments', 0);
        Http::assertNothingSent();
    }

    public function test_service_custom_hours_override_workspace_hours(): void
    {
        $this->travelTo('2026-10-31 12:00:00');
        [$workspace] = $this->configuredWorkspace();
        $service = Service::factory()->for($workspace)->create([
            'duration_minutes' => 45,
            'uses_custom_hours' => true,
        ]);
        ServiceBusinessHour::factory()->for($service)->create([
            'day_of_week' => 1,
            'opens_at' => '12:00',
            'closes_at' => '16:00',
        ]);
        Http::preventStrayRequests();
        $attributes = $this->appointmentData();
        $attributes['service_id'] = $service->id;

        try {
            app(CreateAppointment::class)->handle($workspace, $attributes, 'service-hours-message');
            $this->fail('The service-specific schedule should have rejected the slot.');
        } catch (SlotUnavailableException $exception) {
            $this->assertSame('That time is outside your configured service availability.', $exception->getMessage());
        }

        $this->assertDatabaseCount('appointments', 0);
        Http::assertNothingSent();
    }

    /** @return array{Workspace, CalendarConnection} */
    private function configuredWorkspace(): array
    {
        config()->set('cache.default', 'array');
        $workspace = Workspace::factory()->create([
            'timezone' => 'America/Los_Angeles',
            'appointment_duration_minutes' => 30,
            'buffer_minutes' => 10,
        ]);
        BusinessHour::factory()->for($workspace)->create(['day_of_week' => 1]);
        $connection = CalendarConnection::factory()->for($workspace)->create([
            'calendar_id' => 'primary@example.com',
        ]);

        return [$workspace, $connection];
    }

    /** @return array<string, string|int> */
    private function appointmentData(): array
    {
        return [
            'customer_name' => 'Avery Johnson',
            'customer_phone' => '+15551234567',
            'customer_email' => 'avery@example.com',
            'service' => 'Consultation',
            'starts_at' => '2026-11-02T10:00',
            'notes' => 'First visit',
        ];
    }

    private function fakeAvailableGoogleCalendar(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://www.googleapis.com/calendar/v3/freeBusy' => Http::response([
                'calendars' => ['primary@example.com' => ['busy' => []]],
            ]),
            'https://www.googleapis.com/calendar/v3/calendars/*/events' => Http::response(['id' => 'google-event-123']),
        ]);
    }
}
