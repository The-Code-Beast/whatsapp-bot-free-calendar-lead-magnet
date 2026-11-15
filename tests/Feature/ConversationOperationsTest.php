<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsAppConnection;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConversationOperationsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_mark_read_updates_only_unread_inbound_messages(): void
    {
        $workspace = Workspace::factory()->create();
        $conversation = Conversation::factory()->for($workspace)->create();
        $unread = Message::factory()->for($conversation)->create(['direction' => 'inbound', 'read_at' => null]);
        $outbound = Message::factory()->for($conversation)->create(['direction' => 'outbound', 'read_at' => null]);

        $this->actingAs($workspace->owner)
            ->post(route('conversations.read', $conversation))
            ->assertRedirect();

        $this->assertNotNull($unread->fresh()->read_at);
        $this->assertNull($outbound->fresh()->read_at);
    }

    public function test_user_can_take_over_and_return_conversation_to_bot(): void
    {
        $workspace = Workspace::factory()->create();
        $conversation = Conversation::factory()->for($workspace)->create(['human_takeover' => false]);

        $this->actingAs($workspace->owner)
            ->post(route('conversations.takeover.store', $conversation))
            ->assertSessionHas('success', 'The bot is paused for this conversation.');
        $this->assertTrue($conversation->fresh()->human_takeover);

        $this->actingAs($workspace->owner)
            ->delete(route('conversations.takeover.destroy', $conversation))
            ->assertSessionHas('success', 'The bot can reply to this conversation again.');
        $this->assertFalse($conversation->fresh()->human_takeover);
    }

    public function test_manual_reply_sends_whatsapp_message_stores_it_and_enables_takeover(): void
    {
        config()->set('services.whatsapp.graph_version', 'v23.0');
        $workspace = Workspace::factory()->create();
        $connection = WhatsAppConnection::factory()->for($workspace)->create([
            'phone_number_id' => '123456789',
            'access_token' => 'saved-token',
        ]);
        $conversation = Conversation::factory()->for($workspace)->create([
            'whats_app_connection_id' => $connection->id,
            'customer_wa_id' => '15550001111',
            'human_takeover' => false,
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://graph.facebook.com/v23.0/123456789/messages' => Http::response([
                'messages' => [['id' => 'wamid.manual-reply']],
            ]),
        ]);

        $this->actingAs($workspace->owner)
            ->post(route('conversations.reply', $conversation), ['body' => '  I can help with that.  '])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Reply sent. Human takeover is active.');

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'meta_message_id' => 'wamid.manual-reply',
            'direction' => 'outbound',
            'sender_type' => 'human',
            'body' => 'I can help with that.',
            'status' => 'sent',
        ]);
        $this->assertTrue($conversation->fresh()->human_takeover);
        Http::assertSent(fn (Request $request): bool => $request['to'] === '15550001111'
            && $request['text']['body'] === 'I can help with that.'
            && $request->hasHeader('Authorization', 'Bearer saved-token'));
    }

    public function test_manual_reply_requires_connected_whatsapp_number(): void
    {
        $workspace = Workspace::factory()->create();
        $conversation = Conversation::factory()->for($workspace)->create();
        Http::preventStrayRequests();

        $this->actingAs($workspace->owner)
            ->post(route('conversations.reply', $conversation), ['body' => 'Hello'])
            ->assertSessionHasErrors(['body' => 'Reconnect WhatsApp before sending a reply.']);

        $this->assertDatabaseCount('messages', 0);
        Http::assertNothingSent();
    }

    public function test_manual_reply_rejects_empty_body_before_http_request(): void
    {
        $workspace = Workspace::factory()->create();
        $conversation = Conversation::factory()->for($workspace)->create();
        Http::preventStrayRequests();

        $this->actingAs($workspace->owner)
            ->post(route('conversations.reply', $conversation), ['body' => ''])
            ->assertSessionHasErrors('body');

        Http::assertNothingSent();
    }

    public function test_conversation_operations_return_not_found_for_another_workspace(): void
    {
        $workspace = Workspace::factory()->create();
        $otherConversation = Conversation::factory()->create();
        Http::preventStrayRequests();

        $this->actingAs($workspace->owner)
            ->post(route('conversations.read', $otherConversation))
            ->assertNotFound();
        $this->actingAs($workspace->owner)
            ->post(route('conversations.takeover.store', $otherConversation))
            ->assertNotFound();
        $this->actingAs($workspace->owner)
            ->post(route('conversations.reply', $otherConversation), ['body' => 'Hello'])
            ->assertNotFound();

        Http::assertNothingSent();
    }
}
