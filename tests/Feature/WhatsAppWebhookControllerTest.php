<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\BusinessHour;
use App\Models\CalendarConnection;
use App\Models\Conversation;
use App\Models\Service;
use App\Models\WhatsAppConnection;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class WhatsAppWebhookControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_meta_can_verify_the_webhook_with_the_configured_token(): void
    {
        config()->set('services.whatsapp.verify_token', 'verify-me');

        $this->get(route('webhooks.whatsapp.verify', [
            'hub.mode' => 'subscribe',
            'hub.verify_token' => 'verify-me',
            'hub.challenge' => 'challenge-value',
        ]))->assertOk()->assertSeeText('challenge-value');
    }

    public function test_webhook_verification_rejects_an_invalid_token(): void
    {
        config()->set('services.whatsapp.verify_token', 'verify-me');

        $this->get(route('webhooks.whatsapp.verify', [
            'hub.mode' => 'subscribe',
            'hub.verify_token' => 'wrong-token',
            'hub.challenge' => 'challenge-value',
        ]))->assertForbidden();
    }

    public function test_message_webhook_rejects_a_missing_signature(): void
    {
        config()->set('services.whatsapp.app_secret', 'app-secret');

        $this->postJson(route('webhooks.whatsapp.handle'), [
            'object' => 'whatsapp_business_account',
        ])->assertUnauthorized();
    }

    public function test_disabled_bot_records_incoming_message_without_replying(): void
    {
        $workspace = Workspace::factory()->create(['bot_enabled' => false]);
        $connection = WhatsAppConnection::factory()->for($workspace)->create();
        Http::preventStrayRequests();

        $this->postSignedWebhook($this->messagePayload($connection, 'wamid.disabled', 'Hello'))
            ->assertOk()
            ->assertSeeText('EVENT_RECEIVED');

        $this->assertDatabaseHas('messages', [
            'meta_message_id' => 'wamid.disabled',
            'direction' => 'inbound',
            'status' => 'processed',
        ]);
        $this->assertDatabaseCount('messages', 1);
        Http::assertNothingSent();
    }

    public function test_duplicate_delivery_does_not_send_a_duplicate_reply(): void
    {
        $workspace = Workspace::factory()->create(['bot_enabled' => true]);
        $connection = WhatsAppConnection::factory()->for($workspace)->create();
        Http::preventStrayRequests();
        Http::fake([
            'https://graph.facebook.com/v23.0/*/messages' => Http::response([
                'messages' => [['id' => 'wamid.reply']],
            ]),
        ]);
        $payload = $this->messagePayload($connection, 'wamid.duplicate', 'Hello');

        $this->postSignedWebhook($payload)->assertOk();
        $this->postSignedWebhook($payload)->assertOk();

        $this->assertDatabaseCount('messages', 2);
        $this->assertDatabaseCount('conversations', 1);
        Http::assertSentCount(1);
    }

    public function test_automatic_language_detection_starts_and_remembers_a_spanish_conversation(): void
    {
        $workspace = Workspace::factory()->create(['bot_enabled' => true, 'assistant_language' => 'auto']);
        $connection = WhatsAppConnection::factory()->for($workspace)->create();
        Service::factory()->for($workspace)->create(['name' => 'Consulta']);
        Http::preventStrayRequests();
        Http::fake([
            'https://graph.facebook.com/v23.0/*/messages' => Http::response([
                'messages' => [['id' => 'wamid.spanish-reply']],
            ]),
        ]);

        $this->postSignedWebhook($this->messagePayload($connection, 'wamid.spanish-in', 'Hola, quiero reservar una cita'))->assertOk();

        $conversation = $workspace->conversations()->firstOrFail();
        $this->assertSame('es', $conversation->booking_data['language']);
        $this->assertDatabaseHas('messages', [
            'meta_message_id' => 'wamid.spanish-reply',
            'body' => "¡Hola Taylor Client! Puedo ayudarte a reservar una cita. Responde con el número del servicio que deseas:\n\n1. Consulta (30 min)",
        ]);
    }

    public function test_workspace_can_force_spanish_for_an_english_customer_message(): void
    {
        $workspace = Workspace::factory()->create(['bot_enabled' => true, 'assistant_language' => 'es']);
        $connection = WhatsAppConnection::factory()->for($workspace)->create();
        Service::factory()->for($workspace)->create(['name' => 'Consultation']);
        Http::preventStrayRequests();
        Http::fake([
            'https://graph.facebook.com/v23.0/*/messages' => Http::response([
                'messages' => [['id' => 'wamid.forced-spanish-reply']],
            ]),
        ]);

        $this->postSignedWebhook($this->messagePayload($connection, 'wamid.forced-spanish-in', 'Hello'))->assertOk();

        $this->assertDatabaseHas('messages', [
            'meta_message_id' => 'wamid.forced-spanish-reply',
            'body' => "¡Hola Taylor Client! Puedo ayudarte a reservar una cita. Responde con el número del servicio que deseas:\n\n1. Consultation (30 min)",
        ]);
    }

    public function test_bot_rejects_service_that_is_not_in_active_catalog(): void
    {
        $workspace = Workspace::factory()->create(['bot_enabled' => true]);
        $connection = WhatsAppConnection::factory()->for($workspace)->create();
        Service::factory()->for($workspace)->create(['name' => 'Consultation']);
        Http::preventStrayRequests();
        Http::fake([
            'https://graph.facebook.com/v23.0/*/messages' => Http::sequence()
                ->push(['messages' => [['id' => 'wamid.catalog-1']]])
                ->push(['messages' => [['id' => 'wamid.catalog-2']]]),
        ]);

        $this->postSignedWebhook($this->messagePayload($connection, 'wamid.catalog-in-1', 'Hello'))->assertOk();
        $this->postSignedWebhook($this->messagePayload($connection, 'wamid.catalog-in-2', 'Haircut'))->assertOk();

        $this->assertDatabaseHas('conversations', [
            'workspace_id' => $workspace->id,
            'booking_stage' => 'awaiting_service',
        ]);
        $this->assertDatabaseMissing('appointments', ['workspace_id' => $workspace->id]);
        $this->assertDatabaseHas('messages', [
            'meta_message_id' => 'wamid.catalog-2',
            'body' => "Please reply with one of the service numbers:\n\n1. Consultation",
        ]);
        Http::assertSentCount(2);
    }

    public function test_text_conversation_books_available_google_calendar_slot(): void
    {
        $this->travelTo('2026-10-31 12:00:00');
        config()->set('cache.default', 'array');
        $workspace = Workspace::factory()->create([
            'bot_enabled' => true,
            'timezone' => 'America/Los_Angeles',
            'appointment_duration_minutes' => 30,
            'buffer_minutes' => 0,
        ]);
        $whatsAppConnection = WhatsAppConnection::factory()->for($workspace)->create();
        $calendarConnection = CalendarConnection::factory()->for($workspace)->create([
            'calendar_id' => 'primary@example.com',
        ]);
        BusinessHour::factory()->for($workspace)->create(['day_of_week' => 1]);
        Service::factory()->for($workspace)->create([
            'name' => 'Consultation',
            'duration_minutes' => 45,
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://graph.facebook.com/v23.0/*/messages' => Http::sequence()
                ->push(['messages' => [['id' => 'wamid.reply-1']]])
                ->push(['messages' => [['id' => 'wamid.reply-2']]])
                ->push(['messages' => [['id' => 'wamid.reply-3']]]),
            'https://www.googleapis.com/calendar/v3/freeBusy' => Http::response([
                'calendars' => [$calendarConnection->calendar_id => ['busy' => []]],
            ]),
            'https://www.googleapis.com/calendar/v3/calendars/*/events' => Http::response(['id' => 'google-event-id']),
        ]);

        $this->postSignedWebhook($this->messagePayload($whatsAppConnection, 'wamid.1', 'Hello'))->assertOk();
        $this->postSignedWebhook($this->messagePayload($whatsAppConnection, 'wamid.2', '1'))->assertOk();
        $this->postSignedWebhook($this->messagePayload($whatsAppConnection, 'wamid.3', '1'))->assertOk();

        $this->assertDatabaseHas('appointments', [
            'workspace_id' => $workspace->id,
            'customer_name' => 'Taylor Client',
            'customer_phone' => '15550009999',
            'service' => 'Consultation',
            'status' => 'confirmed',
            'google_event_id' => 'google-event-id',
            'idempotency_key' => 'wamid.3',
            'starts_at' => '2026-11-02 17:00:00',
            'ends_at' => '2026-11-02 17:45:00',
        ]);
        $this->assertDatabaseHas('customers', [
            'workspace_id' => $workspace->id,
            'name' => 'Taylor Client',
            'phone' => '15550009999',
            'status' => 'booked',
            'source' => 'whatsapp',
        ]);
        $this->assertDatabaseHas('conversations', [
            'workspace_id' => $workspace->id,
            'customer_wa_id' => '15550009999',
            'status' => 'booked',
            'booking_stage' => 'completed',
        ]);
        $this->assertDatabaseCount('messages', 6);
        $this->assertStringContainsString(
            'Reply with 1, 2, or 3 to choose a time:',
            $workspace->conversations()->firstOrFail()->messages()->where('direction', 'outbound')->orderBy('id')->skip(1)->value('body'),
        );
        Http::assertSentCount(8);
    }

    public function test_booked_customer_can_reschedule_through_whatsapp(): void
    {
        $this->travelTo('2026-10-31 12:00:00');
        $workspace = Workspace::factory()->create(['bot_enabled' => true, 'timezone' => 'America/Los_Angeles']);
        $whatsAppConnection = WhatsAppConnection::factory()->for($workspace)->create();
        $calendarConnection = CalendarConnection::factory()->for($workspace)->create(['calendar_id' => 'primary@example.com']);
        BusinessHour::factory()->for($workspace)->create(['day_of_week' => 1]);
        $service = Service::factory()->for($workspace)->create(['duration_minutes' => 30]);
        $appointment = Appointment::factory()->for($workspace)->for($service, 'serviceModel')->create([
            'calendar_connection_id' => $calendarConnection->id,
            'starts_at' => '2026-11-02 18:00:00',
            'ends_at' => '2026-11-02 18:30:00',
            'google_event_id' => 'google-event-id',
        ]);
        Conversation::factory()->for($workspace)->create([
            'whats_app_connection_id' => $whatsAppConnection->id,
            'customer_wa_id' => '15550009999',
            'status' => 'booked',
            'booking_stage' => 'completed',
            'booking_data' => ['appointment_id' => $appointment->id, 'service_id' => $service->id],
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://graph.facebook.com/v23.0/*/messages' => Http::sequence()
                ->push(['messages' => [['id' => 'wamid.reschedule-1']]])
                ->push(['messages' => [['id' => 'wamid.reschedule-2']]]),
            'https://www.googleapis.com/calendar/v3/freeBusy' => Http::response(['calendars' => [$calendarConnection->calendar_id => ['busy' => []]]]),
            'https://www.googleapis.com/calendar/v3/calendars/*/events/*' => Http::response(['id' => 'google-event-id']),
        ]);

        $this->postSignedWebhook($this->messagePayload($whatsAppConnection, 'wamid.reschedule-in-1', 'RESCHEDULE'))->assertOk();
        $this->postSignedWebhook($this->messagePayload($whatsAppConnection, 'wamid.reschedule-in-2', '1'))->assertOk();

        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'starts_at' => '2026-11-02 17:00:00']);
        $this->assertDatabaseHas('conversations', ['workspace_id' => $workspace->id, 'booking_stage' => 'completed']);
        $this->assertStringContainsString(
            'Reply with 1, 2, or 3 to choose a new time:',
            $workspace->conversations()->firstOrFail()->messages()->where('direction', 'outbound')->oldest()->value('body'),
        );
        Http::assertSentCount(7);
    }

    public function test_booked_customer_can_cancel_through_whatsapp(): void
    {
        $workspace = Workspace::factory()->create(['bot_enabled' => true]);
        $whatsAppConnection = WhatsAppConnection::factory()->for($workspace)->create();
        $calendarConnection = CalendarConnection::factory()->for($workspace)->create(['calendar_id' => 'primary@example.com']);
        $appointment = Appointment::factory()->for($workspace)->create([
            'calendar_connection_id' => $calendarConnection->id,
            'google_event_id' => 'google-event-id',
        ]);
        Conversation::factory()->for($workspace)->create([
            'whats_app_connection_id' => $whatsAppConnection->id,
            'customer_wa_id' => '15550009999',
            'status' => 'booked',
            'booking_stage' => 'completed',
            'booking_data' => ['appointment_id' => $appointment->id],
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://graph.facebook.com/v23.0/*/messages' => Http::response(['messages' => [['id' => 'wamid.cancel-1']]]),
            'https://www.googleapis.com/calendar/v3/calendars/*/events/*' => Http::response([], 204),
        ]);

        $this->postSignedWebhook($this->messagePayload($whatsAppConnection, 'wamid.cancel-in-1', 'CANCEL'))->assertOk();

        $this->assertSame('cancelled', $appointment->fresh()->status);
        $this->assertDatabaseHas('conversations', ['workspace_id' => $workspace->id, 'status' => 'cancelled']);
        Http::assertSentCount(2);
    }

    /** @return array<string, mixed> */
    private function messagePayload(WhatsAppConnection $connection, string $messageId, string $body): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => $connection->waba_id,
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => ['phone_number_id' => $connection->phone_number_id],
                        'contacts' => [['profile' => ['name' => 'Taylor Client'], 'wa_id' => '15550009999']],
                        'messages' => [[
                            'from' => '15550009999',
                            'id' => $messageId,
                            'timestamp' => '1793476800',
                            'text' => ['body' => $body],
                            'type' => 'text',
                        ]],
                    ],
                ]],
            ]],
        ];
    }

    /** @param array<string, mixed> $payload */
    private function postSignedWebhook(array $payload): TestResponse
    {
        config()->set('services.whatsapp.app_secret', 'app-secret');
        config()->set('services.whatsapp.graph_version', 'v23.0');
        $content = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = 'sha256='.hash_hmac('sha256', $content, 'app-secret');

        return $this->call('POST', route('webhooks.whatsapp.handle'), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
        ], content: $content);
    }
}
