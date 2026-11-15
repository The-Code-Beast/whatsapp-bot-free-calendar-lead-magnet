<?php

namespace App\Actions;

use App\Exceptions\SlotUnavailableException;
use App\Models\Appointment;
use App\Models\CalendarConnection;
use App\Models\Service;
use App\Models\Workspace;
use App\Services\GoogleCalendarClient;
use Carbon\CarbonImmutable;

class EnsureAppointmentSlotAvailable
{
    public function __construct(private GoogleCalendarClient $googleCalendar) {}

    public function handle(
        Workspace $workspace,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        ?Service $service = null,
        ?Appointment $except = null,
        bool $checkGoogle = true,
    ): CalendarConnection {
        if ($startsAt->isPast()) {
            throw new SlotUnavailableException('Choose a time in the future.');
        }

        $hoursQuery = $service?->uses_custom_hours ? $service->businessHours() : $workspace->businessHours();
        $hours = $hoursQuery->where('day_of_week', $startsAt->dayOfWeek)->where('is_enabled', true)->first();

        if ($hours === null
            || $startsAt->format('H:i') < mb_substr($hours->opens_at, 0, 5)
            || $endsAt->format('H:i') > mb_substr($hours->closes_at, 0, 5)
        ) {
            $schedule = $service?->uses_custom_hours ? 'service availability' : 'business hours';

            throw new SlotUnavailableException("That time is outside your configured {$schedule}.");
        }

        if ($workspace->availabilityBlocks()->where('starts_at', '<', $endsAt->utc())->where('ends_at', '>', $startsAt->utc())->exists()) {
            throw new SlotUnavailableException('That time has been blocked off. Please choose another time.');
        }

        $connection = $workspace->calendarConnection;

        if ($connection?->status !== 'connected' || blank($connection->calendar_id)) {
            throw new SlotUnavailableException('Connect and select a Google Calendar before creating appointments.');
        }

        $bufferedStart = $startsAt->subMinutes($workspace->buffer_minutes);
        $bufferedEnd = $endsAt->addMinutes($workspace->buffer_minutes);
        $conflicts = $workspace->appointments()
            ->whereIn('status', ['pending', 'confirmed'])
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->where('starts_at', '<', $bufferedEnd->utc())
            ->where('ends_at', '>', $bufferedStart->utc())
            ->exists();

        if ($conflicts || ($checkGoogle && ! $this->googleCalendar->isAvailable($connection, $bufferedStart, $bufferedEnd))) {
            throw new SlotUnavailableException;
        }

        return $connection;
    }
}
