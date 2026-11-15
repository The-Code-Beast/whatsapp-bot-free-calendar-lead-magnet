<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateWorkspaceRequest;
use App\Services\GoogleCalendarClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class WorkspaceSettingsController extends Controller
{
    public function edit(Request $request, GoogleCalendarClient $googleCalendar): Response
    {
        $workspace = $request->user()->workspace?->load(['businessHours', 'calendarConnection']);
        $connection = $workspace?->calendarConnection;
        $calendars = [];
        $calendarError = null;

        if ($connection?->status === 'connected') {
            try {
                $calendars = $googleCalendar->calendars($connection);
            } catch (Throwable) {
                $calendarError = 'We could not reach Google Calendar. Reconnect the account and try again.';
            }
        }

        return Inertia::render('Settings/Workspace', [
            'workspace' => $workspace ? [
                'name' => $workspace->name,
                'timezone' => $workspace->timezone,
                'appointment_duration_minutes' => $workspace->appointment_duration_minutes,
                'buffer_minutes' => $workspace->buffer_minutes,
                'bot_tone' => $workspace->bot_tone,
                'assistant_language' => $workspace->assistant_language,
                'bot_instructions' => $workspace->bot_instructions,
                'business_hours' => $workspace->businessHours->map->only([
                    'day_of_week',
                    'opens_at',
                    'closes_at',
                    'is_enabled',
                ])->values(),
            ] : null,
            'calendarConnection' => $connection ? [
                'email' => $connection->email,
                'calendar_id' => $connection->calendar_id,
                'calendar_name' => $connection->calendar_name,
                'status' => $connection->status,
            ] : null,
            'calendars' => $calendars,
            'calendarError' => $calendarError,
            'googleConfigured' => $googleCalendar->isConfigured(),
            'timezones' => collect(timezone_identifiers_list())
                ->filter(fn (string $timezone): bool => str_contains($timezone, '/'))
                ->values(),
        ]);
    }

    public function update(UpdateWorkspaceRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $businessHours = $validated['business_hours'];
        unset($validated['business_hours']);

        DB::transaction(function () use ($request, $validated, $businessHours): void {
            $workspace = $request->user()->workspace()->updateOrCreate([], $validated);

            foreach ($businessHours as $hours) {
                $workspace->businessHours()->updateOrCreate(
                    ['day_of_week' => $hours['day_of_week']],
                    [
                        'is_enabled' => $hours['is_enabled'],
                        'opens_at' => $hours['is_enabled'] ? $hours['opens_at'] : null,
                        'closes_at' => $hours['is_enabled'] ? $hours['closes_at'] : null,
                    ],
                );
            }
        });

        return back()->with('success', __('messages.workspace_saved'));
    }
}
