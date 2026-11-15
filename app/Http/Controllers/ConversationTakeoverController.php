<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ConversationTakeoverController extends Controller
{
    public function store(Request $request, int $conversation): RedirectResponse
    {
        $conversationModel = $request->user()->workspace?->conversations()->findOrFail($conversation);
        abort_if($conversationModel === null, 404);
        $conversationModel->update(['human_takeover' => true]);

        return back()->with('success', __('messages.bot_paused'));
    }

    public function destroy(Request $request, int $conversation): RedirectResponse
    {
        $conversationModel = $request->user()->workspace?->conversations()->findOrFail($conversation);
        abort_if($conversationModel === null, 404);
        $conversationModel->update(['human_takeover' => false]);

        return back()->with('success', __('messages.bot_resumed'));
    }
}
