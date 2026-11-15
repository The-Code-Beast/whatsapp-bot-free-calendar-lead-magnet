<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ConversationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get(route('conversations.index'))->assertRedirect(route('login'));
    }

    public function test_index_lists_only_workspace_conversations_with_unread_counts(): void
    {
        $workspace = Workspace::factory()->create();
        $olderConversation = Conversation::factory()->for($workspace)->create([
            'customer_name' => 'Older Customer',
            'last_message_at' => now()->subHour(),
        ]);
        $newerConversation = Conversation::factory()->for($workspace)->create([
            'customer_name' => 'Newer Customer',
            'last_message_at' => now(),
        ]);
        $otherConversation = Conversation::factory()->create(['customer_name' => 'Other Workspace']);
        Message::factory()->for($newerConversation)->create(['body' => 'Unread message', 'read_at' => null]);
        Message::factory()->for($newerConversation)->create(['body' => 'Read message', 'read_at' => now()]);
        Message::factory()->for($olderConversation)->create(['body' => 'Earlier message', 'read_at' => now()]);

        $this->actingAs($workspace->owner)
            ->get(route('conversations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Conversations/Index')
                ->has('conversations.data', 2)
                ->where('conversations.data.0.customer_name', 'Newer Customer')
                ->where('conversations.data.0.unread_count', 1)
                ->where('conversations.data.0.latest_message.body', 'Read message')
                ->where('conversations.data.1.customer_name', 'Older Customer'));

        $this->assertNotSame($workspace->id, $otherConversation->workspace_id);
    }

    public function test_index_searches_customer_name_and_phone(): void
    {
        $workspace = Workspace::factory()->create();
        Conversation::factory()->for($workspace)->create(['customer_name' => 'Avery Johnson', 'customer_wa_id' => '15550001111']);
        Conversation::factory()->for($workspace)->create(['customer_name' => 'Taylor Smith', 'customer_wa_id' => '15559998888']);

        $this->actingAs($workspace->owner)
            ->get(route('conversations.index', ['search' => 'Avery']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('conversations.data', 1)
                ->where('conversations.data.0.customer_name', 'Avery Johnson')
                ->where('search', 'Avery'));

        $this->actingAs($workspace->owner)
            ->get(route('conversations.index', ['search' => '9998888']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('conversations.data', 1)
                ->where('conversations.data.0.customer_name', 'Taylor Smith'));
    }

    public function test_show_displays_selected_conversation_messages_in_chronological_order(): void
    {
        $workspace = Workspace::factory()->create();
        $conversation = Conversation::factory()->for($workspace)->create();
        $laterMessage = Message::factory()->for($conversation)->create([
            'body' => 'Second message',
            'sent_at' => now(),
        ]);
        $earlierMessage = Message::factory()->for($conversation)->create([
            'body' => 'First message',
            'sent_at' => now()->subMinute(),
        ]);

        $this->actingAs($workspace->owner)
            ->get(route('conversations.show', $conversation))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('selectedConversation.id', $conversation->id)
                ->has('selectedConversation.messages', 2)
                ->where('selectedConversation.messages.0.id', $earlierMessage->id)
                ->where('selectedConversation.messages.1.id', $laterMessage->id));
    }

    public function test_show_returns_not_found_for_another_workspace_conversation(): void
    {
        $workspace = Workspace::factory()->create();
        $otherConversation = Conversation::factory()->create();

        $this->actingAs($workspace->owner)
            ->get(route('conversations.show', $otherConversation))
            ->assertNotFound();
    }
}
