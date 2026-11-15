<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\CalendarConnection;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleCalendarClient
{
    private const AUTHORIZATION_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const CALENDAR_LIST_URL = 'https://www.googleapis.com/calendar/v3/users/me/calendarList';

    private const FREE_BUSY_URL = 'https://www.googleapis.com/calendar/v3/freeBusy';

    /** @var list<string> */
    private const SCOPES = [
        'https://www.googleapis.com/auth/calendar.calendarlist.readonly',
        'https://www.googleapis.com/auth/calendar.events',
        'https://www.googleapis.com/auth/calendar.events.freebusy',
    ];

    public function isConfigured(): bool
    {
        return filled(config('services.google_calendar.client_id'))
            && filled(config('services.google_calendar.client_secret'));
    }

    public function authorizationUrl(string $state): string
    {
        return self::AUTHORIZATION_URL.'?'.http_build_query([
            'client_id' => config('services.google_calendar.client_id'),
            'redirect_uri' => config('services.google_calendar.redirect_uri'),
            'response_type' => 'code',
            'scope' => implode(' ', self::SCOPES),
            'access_type' => 'offline',
            'include_granted_scopes' => 'true',
            'prompt' => 'consent',
            'state' => $state,
        ], encoding_type: PHP_QUERY_RFC3986);
    }

    /** @return array{access_token: string, refresh_token?: string, expires_in: int, scope?: string, token_type?: string} */
    public function exchangeCode(string $code): array
    {
        /** @var array{access_token: string, refresh_token?: string, expires_in: int, scope?: string, token_type?: string} $tokens */
        $tokens = $this->request()
            ->asForm()
            ->post(self::TOKEN_URL, [
                'client_id' => config('services.google_calendar.client_id'),
                'client_secret' => config('services.google_calendar.client_secret'),
                'redirect_uri' => config('services.google_calendar.redirect_uri'),
                'grant_type' => 'authorization_code',
                'code' => $code,
            ])
            ->throw()
            ->json();

        return $tokens;
    }

    /** @return list<array{id: string, summary: string, primary: bool, access_role: string}> */
    public function calendars(CalendarConnection $connection): array
    {
        $this->refreshAccessTokenIfNeeded($connection);

        $items = $this->request()
            ->withToken($connection->access_token)
            ->get(self::CALENDAR_LIST_URL, ['minAccessRole' => 'writer'])
            ->throw()
            ->collect('items');

        return $items
            ->map(fn (array $calendar): array => [
                'id' => $calendar['id'],
                'summary' => $calendar['summary'] ?? $calendar['id'],
                'primary' => (bool) ($calendar['primary'] ?? false),
                'access_role' => $calendar['accessRole'] ?? 'reader',
            ])
            ->values()
            ->all();
    }

    public function isAvailable(CalendarConnection $connection, CarbonInterface $startsAt, CarbonInterface $endsAt): bool
    {
        $this->refreshAccessTokenIfNeeded($connection);

        $response = $this->request()
            ->withToken($connection->access_token)
            ->post(self::FREE_BUSY_URL, [
                'timeMin' => $startsAt->toRfc3339String(),
                'timeMax' => $endsAt->toRfc3339String(),
                'timeZone' => $connection->workspace->timezone,
                'items' => [['id' => $connection->calendar_id]],
            ])
            ->throw();

        $calendars = $response->json('calendars', []);
        $busyPeriods = $calendars[$connection->calendar_id]['busy'] ?? [];

        return $busyPeriods === [];
    }

    public function createEvent(CalendarConnection $connection, Appointment $appointment): string
    {
        $this->refreshAccessTokenIfNeeded($connection);
        $googleEventId = 'booking'.hash('sha256', $appointment->idempotency_key);
        $event = $this->request()
            ->withToken($connection->access_token)
            ->post($this->eventUrl($connection), array_merge($this->eventPayload($appointment), [
                'id' => $googleEventId,
            ]))
            ->throw()
            ->json('id');

        if (! is_string($event) || $event === '') {
            throw new RuntimeException('Google Calendar did not return an event identifier.');
        }

        return $event;
    }

    public function updateEvent(CalendarConnection $connection, Appointment $appointment): void
    {
        $this->refreshAccessTokenIfNeeded($connection);

        $this->request()
            ->withToken($connection->access_token)
            ->put($this->eventUrl($connection, $appointment->google_event_id), $this->eventPayload($appointment))
            ->throw();
    }

    public function deleteEvent(CalendarConnection $connection, Appointment $appointment): void
    {
        $this->refreshAccessTokenIfNeeded($connection);

        $this->request()
            ->withToken($connection->access_token)
            ->delete($this->eventUrl($connection, $appointment->google_event_id), ['sendUpdates' => 'all'])
            ->throw();
    }

    private function eventUrl(CalendarConnection $connection, ?string $eventId = null): string
    {
        $url = 'https://www.googleapis.com/calendar/v3/calendars/'.rawurlencode($connection->calendar_id).'/events';

        return $eventId ? $url.'/'.rawurlencode($eventId) : $url;
    }

    /** @return array<string, mixed> */
    private function eventPayload(Appointment $appointment): array
    {
        return [
            'summary' => ($appointment->service ?: 'Appointment').' — '.$appointment->customer_name,
            'description' => $appointment->notes,
            'start' => ['dateTime' => $appointment->starts_at->toRfc3339String(), 'timeZone' => $appointment->timezone],
            'end' => ['dateTime' => $appointment->ends_at->toRfc3339String(), 'timeZone' => $appointment->timezone],
            'attendees' => $appointment->customer_email ? [['email' => $appointment->customer_email, 'displayName' => $appointment->customer_name]] : [],
            'extendedProperties' => [
                'private' => [
                    'bookline_appointment_id' => $appointment->public_id,
                    'bookline_idempotency_key' => $appointment->idempotency_key,
                ],
            ],
        ];
    }

    private function refreshAccessTokenIfNeeded(CalendarConnection $connection): void
    {
        if ($connection->token_expires_at?->isFuture()) {
            return;
        }

        if (blank($connection->refresh_token)) {
            throw new RuntimeException('Google Calendar authorization has expired.');
        }

        $tokens = $this->request()
            ->asForm()
            ->post(self::TOKEN_URL, [
                'client_id' => config('services.google_calendar.client_id'),
                'client_secret' => config('services.google_calendar.client_secret'),
                'grant_type' => 'refresh_token',
                'refresh_token' => $connection->refresh_token,
            ])
            ->throw()
            ->json();

        $connection->update([
            'access_token' => $tokens['access_token'],
            'token_expires_at' => now()->addSeconds((int) $tokens['expires_in']),
            'status' => 'connected',
        ]);
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()->connectTimeout(5)->timeout(15);
    }
}
