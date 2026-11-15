<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReadConversationController extends Controller
{
    public function __invoke(Request $request, int $conversation): RedirectResponse
    {
        $conversationModel = $request->user()->workspace?->conversations()->findOrFail($conversation);
        abort_if($conversationModel === null, 404);

        $conversationModel->messages()
            ->where('direction', 'inbound')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back();
    }
}
