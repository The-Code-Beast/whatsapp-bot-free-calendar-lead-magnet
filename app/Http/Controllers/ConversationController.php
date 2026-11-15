<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->renderInbox($request);
    }

    public function show(Request $request, int $conversation): Response
    {
        $workspace = $request->user()->workspace;
        abort_if($workspace === null, 404);

        $selectedConversation = $workspace->conversations()
            ->with('whatsAppConnection:id,status')
            ->withCount([
                'messages as unread_count' => fn (Builder $query): Builder => $query
                    ->where('direction', 'inbound')
                    ->whereNull('read_at'),
            ])
            ->findOrFail($conversation);

        $messages = $selectedConversation->messages()
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'direction', 'sender_type', 'body', 'status', 'sent_at', 'read_at'])
            ->reverse()
            ->values()
            ->map(fn ($message): array => [
                'id' => $message->id,
                'direction' => $message->direction,
                'sender_type' => $message->sender_type,
                'body' => $message->body,
                'status' => $message->status,
                'sent_at' => $message->sent_at?->toIso8601String(),
                'read_at' => $message->read_at?->toIso8601String(),
            ]);

        return $this->renderInbox($request, [
            'id' => $selectedConversation->id,
            'customer_name' => $selectedConversation->customer_name,
            'customer_wa_id' => $selectedConversation->customer_wa_id,
            'status' => $selectedConversation->status,
            'booking_stage' => $selectedConversation->booking_stage,
            'booking_data' => $selectedConversation->booking_data,
            'human_takeover' => $selectedConversation->human_takeover,
            'last_message_at' => $selectedConversation->last_message_at?->toIso8601String(),
            'unread_count' => $selectedConversation->unread_count,
            'connection_ready' => $selectedConversation->whatsAppConnection?->status === 'connected',
            'messages' => $messages,
        ]);
    }

    /** @param  array<string, mixed>|null  $selectedConversation */
    private function renderInbox(Request $request, ?array $selectedConversation = null): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $workspace = $request->user()->workspace;
        $search = trim($validated['search'] ?? '');
        $conversations = $workspace?->conversations()
            ->select(['id', 'customer_name', 'customer_wa_id', 'status', 'booking_stage', 'human_takeover', 'last_message_at'])
            ->with('latestMessage:id,conversation_id,body,direction,sent_at')
            ->withCount([
                'messages as unread_count' => fn (Builder $query): Builder => $query
                    ->where('direction', 'inbound')
                    ->whereNull('read_at'),
            ])
            ->when($search !== '', fn (Builder $query): Builder => $query->where(function (Builder $query) use ($search): void {
                $query->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_wa_id', 'like', "%{$search}%");
            }))
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Conversation $conversation): array => [
                'id' => $conversation->id,
                'customer_name' => $conversation->customer_name,
                'customer_wa_id' => $conversation->customer_wa_id,
                'status' => $conversation->status,
                'booking_stage' => $conversation->booking_stage,
                'human_takeover' => $conversation->human_takeover,
                'last_message_at' => $conversation->last_message_at?->toIso8601String(),
                'unread_count' => $conversation->unread_count,
                'latest_message' => $conversation->latestMessage ? [
                    'body' => $conversation->latestMessage->body,
                    'direction' => $conversation->latestMessage->direction,
                    'sent_at' => $conversation->latestMessage->sent_at?->toIso8601String(),
                ] : null,
            ]);

        return Inertia::render('Conversations/Index', [
            'conversations' => $conversations,
            'selectedConversation' => $selectedConversation,
            'hasWorkspace' => $workspace !== null,
            'search' => $search,
        ]);
    }
}
