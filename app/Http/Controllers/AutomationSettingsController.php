<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAutomationSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AutomationSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $workspace = $request->user()->workspace;

        return Inertia::render('Automations/Edit', [
            'settings' => $workspace ? [
                'reminder_enabled' => $workspace->reminder_enabled,
                'reminder_hours_before' => $workspace->reminder_hours_before,
                'reminder_message' => $workspace->reminder_message,
                'followup_enabled' => $workspace->followup_enabled,
                'followup_hours_after' => $workspace->followup_hours_after,
                'followup_message' => $workspace->followup_message,
                'timezone' => $workspace->timezone,
            ] : null,
            'stats' => $workspace ? [
                'reminders_sent' => $workspace->appointments()->whereNotNull('reminder_sent_at')->count(),
                'followups_sent' => $workspace->appointments()->whereNotNull('followup_sent_at')->count(),
                'upcoming' => $workspace->appointments()->where('status', 'confirmed')->where('starts_at', '>', now())->count(),
            ] : ['reminders_sent' => 0, 'followups_sent' => 0, 'upcoming' => 0],
        ]);
    }

    public function update(UpdateAutomationSettingsRequest $request): RedirectResponse
    {
        $request->user()->workspace->update($request->safe()->only([
            'reminder_enabled',
            'reminder_hours_before',
            'reminder_message',
            'followup_enabled',
            'followup_hours_after',
            'followup_message',
        ]));

        return back()->with('success', __('messages.automations_saved'));
    }
}
