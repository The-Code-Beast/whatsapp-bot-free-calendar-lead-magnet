<?php

namespace Tests\Feature\Actions;

use App\Actions\SendAppointmentAutomations;
use App\Models\Appointment;
use App\Models\WhatsAppConnection;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendAppointmentAutomationsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_due_reminder_and_followup_are_sent_once_with_template_values(): void
    {
        $this->travelTo('2026-11-04 12:00:00');
        config()->set('cache.default', 'array');
        $workspace = Workspace::factory()->create([
            'timezone' => 'America/Los_Angeles',
            'reminder_enabled' => true,
            'reminder_hours_before' => 4,
            'reminder_message' => 'Reminder: {name} has {service} on {date} at {time}.',
            'followup_enabled' => true,
            'followup_hours_after' => 2,
            'followup_message' => 'Thanks {name} for your {service}.',
        ]);
        WhatsAppConnection::factory()->for($workspace)->create();
        $reminder = Appointment::factory()->for($workspace)->create([
            'customer_name' => 'Taylor Client',
            'customer_phone' => '15550001111',
            'service' => 'Consultation',
            'starts_at' => now()->addHours(3),
            'ends_at' => now()->addHours(3)->addMinutes(30),
            'timezone' => 'America/Los_Angeles',
        ]);
        $followup = Appointment::factory()->for($workspace)->create([
            'customer_name' => 'Morgan Client',
            'customer_phone' => '15550002222',
            'service' => 'Follow-up visit',
            'starts_at' => now()->subHours(4),
            'ends_at' => now()->subHours(3),
            'timezone' => 'America/Los_Angeles',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://graph.facebook.com/v23.0/*/messages' => Http::sequence()
                ->push(['messages' => [['id' => 'wamid.reminder']]])
                ->push(['messages' => [['id' => 'wamid.followup']]]),
        ]);

        $first = app(SendAppointmentAutomations::class)->handle();
        $second = app(SendAppointmentAutomations::class)->handle();

        $this->assertSame(['sent' => 2, 'failed' => 0], $first);
        $this->assertSame(['sent' => 0, 'failed' => 0], $second);
        $this->assertNotNull($reminder->fresh()->reminder_sent_at);
        $this->assertNotNull($followup->fresh()->followup_sent_at);
        $this->assertDatabaseHas('messages', ['meta_message_id' => 'wamid.reminder', 'body' => 'Reminder: Taylor Client has Consultation on Wed, Nov 4, 2026 at 7:00 AM.']);
        $this->assertDatabaseHas('messages', ['meta_message_id' => 'wamid.followup', 'body' => 'Thanks Morgan Client for your Follow-up visit.']);
        Http::assertSentCount(2);
    }

    public function test_disabled_cancelled_and_not_yet_due_appointments_are_skipped(): void
    {
        $this->travelTo('2026-11-04 12:00:00');
        config()->set('cache.default', 'array');
        $workspace = Workspace::factory()->create([
            'reminder_enabled' => false,
            'followup_enabled' => false,
        ]);
        WhatsAppConnection::factory()->for($workspace)->create();
        Appointment::factory()->for($workspace)->create(['starts_at' => now()->addHour(), 'ends_at' => now()->addHours(2)]);
        Appointment::factory()->for($workspace)->create(['status' => 'cancelled', 'starts_at' => now()->addHour(), 'ends_at' => now()->addHours(2)]);
        Http::preventStrayRequests();

        $result = app(SendAppointmentAutomations::class)->handle();

        $this->assertSame(['sent' => 0, 'failed' => 0], $result);
        $this->assertDatabaseCount('messages', 0);
        Http::assertNothingSent();
    }

    public function test_default_reminder_uses_the_workspace_assistant_language(): void
    {
        $this->travelTo('2026-11-04 12:00:00');
        config()->set('cache.default', 'array');
        $workspace = Workspace::factory()->create([
            'assistant_language' => 'es',
            'timezone' => 'America/Los_Angeles',
            'reminder_enabled' => true,
            'reminder_hours_before' => 4,
            'reminder_message' => null,
            'followup_enabled' => false,
        ]);
        WhatsAppConnection::factory()->for($workspace)->create();
        Appointment::factory()->for($workspace)->create([
            'customer_name' => 'Taylor Client',
            'customer_phone' => '15550001111',
            'service' => 'Consulta',
            'starts_at' => now()->addHours(3),
            'ends_at' => now()->addHours(3)->addMinutes(30),
            'timezone' => 'America/Los_Angeles',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://graph.facebook.com/v23.0/*/messages' => Http::response([
                'messages' => [['id' => 'wamid.spanish-reminder']],
            ]),
        ]);

        $result = app(SendAppointmentAutomations::class)->handle();

        $this->assertSame(['sent' => 1, 'failed' => 0], $result);
        $this->assertDatabaseHas('messages', [
            'meta_message_id' => 'wamid.spanish-reminder',
            'body' => 'Hola Taylor Client, te recordamos que tu cita de Consulta es el mié., nov. 4, 2026 a las 7:00 AM. Responde REPROGRAMAR para elegir otro horario o CANCELAR para cancelarla.',
        ]);
    }

    public function test_failed_delivery_is_not_marked_sent_and_can_be_retried(): void
    {
        $this->travelTo('2026-11-04 12:00:00');
        config()->set('cache.default', 'array');
        $workspace = Workspace::factory()->create(['reminder_hours_before' => 4]);
        WhatsAppConnection::factory()->for($workspace)->create();
        $appointment = Appointment::factory()->for($workspace)->create([
            'starts_at' => now()->addHours(2),
            'ends_at' => now()->addHours(3),
        ]);
        Http::preventStrayRequests();
        Http::fake(['https://graph.facebook.com/v23.0/*/messages' => Http::response(['error' => 'unavailable'], 503)]);

        $result = app(SendAppointmentAutomations::class)->handle();

        $this->assertSame(['sent' => 0, 'failed' => 1], $result);
        $this->assertNull($appointment->fresh()->reminder_sent_at);
        $this->assertDatabaseCount('messages', 0);
        Http::assertSentCount(1);
    }
}
