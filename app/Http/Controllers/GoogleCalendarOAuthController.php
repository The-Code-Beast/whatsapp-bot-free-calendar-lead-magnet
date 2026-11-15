<?php

namespace App\Http\Controllers;

use App\Http\Requests\SelectCalendarRequest;
use App\Services\GoogleCalendarClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GoogleCalendarOAuthController extends Controller
{
    public function redirect(Request $request, GoogleCalendarClient $googleCalendar): RedirectResponse
    {
        if ($request->user()->workspace === null) {
            return redirect()->route('workspace.edit')->with('error', __('messages.calendar_save_workspace'));
        }

        if (! $googleCalendar->isConfigured()) {
            return redirect()->route('workspace.edit')->with('error', __('messages.calendar_not_configured'));
        }

        $state = Str::random(64);
        $request->session()->put('google_calendar_oauth_state', $state);

        return redirect()->away($googleCalendar->authorizationUrl($state));
    }

    public function callback(Request $request, GoogleCalendarClient $googleCalendar): RedirectResponse
    {
        if ($request->filled('error')) {
            return redirect()->route('workspace.edit')->with('error', __('messages.calendar_access_denied'));
        }

        $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        $expectedState = $request->session()->pull('google_calendar_oauth_state');

        if (! is_string($expectedState) || ! hash_equals($expectedState, $request->string('state')->toString())) {
            abort(419, 'The Google Calendar authorization session expired.');
        }

        $tokens = $googleCalendar->exchangeCode($request->string('code')->toString());
        $workspace = $request->user()->workspace;
        abort_if($workspace === null, 403);

        $connection = $workspace->calendarConnection()->updateOrCreate([], [
            'provider' => 'google',
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? $workspace->calendarConnection?->refresh_token,
            'token_expires_at' => now()->addSeconds($tokens['expires_in']),
            'status' => 'connected',
        ]);

        $primaryCalendar = collect($googleCalendar->calendars($connection))->firstWhere('primary', true);

        if ($primaryCalendar !== null) {
            $connection->update([
                'provider_account_id' => $primaryCalendar['id'],
                'email' => $primaryCalendar['id'],
                'calendar_id' => $primaryCalendar['id'],
                'calendar_name' => $primaryCalendar['summary'],
            ]);
        }

        return redirect()->route('workspace.edit')->with('success', __('messages.calendar_connected'));
    }

    public function update(SelectCalendarRequest $request, GoogleCalendarClient $googleCalendar): RedirectResponse
    {
        $connection = $request->user()->workspace->calendarConnection;
        $calendar = collect($googleCalendar->calendars($connection))
            ->firstWhere('id', $request->string('calendar_id')->toString());

        if ($calendar === null) {
            throw ValidationException::withMessages([
                'calendar_id' => 'Select a calendar that is available in the connected Google account.',
            ]);
        }

        $connection->update([
            'calendar_id' => $calendar['id'],
            'calendar_name' => $calendar['summary'],
        ]);

        return back()->with('success', __('messages.calendar_updated'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->workspace?->calendarConnection?->delete();

        return back()->with('success', __('messages.calendar_disconnected'));
    }
}
