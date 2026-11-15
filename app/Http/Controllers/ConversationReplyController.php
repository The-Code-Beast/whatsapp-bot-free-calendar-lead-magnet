<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConversationReplyRequest;
use App\Services\WhatsAppClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class ConversationReplyController extends Controller
{
    public function __invoke(StoreConversationReplyRequest $request, int $conversation, WhatsAppClient $whatsApp): RedirectResponse
    {
        $workspace = $request->user()->workspace;
        abort_if($workspace === null, 404);
        $conversationModel = $workspace->conversations()
            ->with('whatsAppConnection')
            ->findOrFail($conversation);
        $connection = $conversationModel->whatsAppConnection;

        if ($connection?->status !== 'connected') {
            throw ValidationException::withMessages([
                'body' => 'Reconnect WhatsApp before sending a reply.',
            ]);
        }

        $body = $request->string('body')->trim()->toString();

        try {
            $metaMessageId = $whatsApp->sendText($connection, $conversationModel->customer_wa_id, $body);
        } catch (ConnectionException|RequestException) {
            throw ValidationException::withMessages([
                'body' => 'WhatsApp could not send this message. Check the connection and try again.',
            ]);
        }

        $conversationModel->messages()->create([
            'meta_message_id' => $metaMessageId !== '' ? $metaMessageId : null,
            'direction' => 'outbound',
            'sender_type' => 'human',
            'type' => 'text',
            'body' => $body,
            'status' => 'sent',
            'sent_at' => now(),
            'read_at' => now(),
        ]);
        $conversationModel->update([
            'human_takeover' => true,
            'last_message_at' => now(),
        ]);

        return back()->with('success', __('messages.reply_sent'));
    }
}
