<?php

namespace App\Actions;

use App\Exceptions\SlotUnavailableException;
use App\Models\Appointment;
use App\Services\GoogleCalendarClient;
use Carbon\CarbonImmutable;

class RescheduleAppointment
{
    public function __construct(
        private EnsureAppointmentSlotAvailable $ensureSlotAvailable,
        private GoogleCalendarClient $googleCalendar,
    ) {}

    public function handle(Appointment $appointment, string $startsAt): Appointment
    {
        if ($appointment->status !== 'confirmed') {
            throw new SlotUnavailableException('Only confirmed appointments can be rescheduled.');
        }

        $workspace = $appointment->workspace;
        $newStart = CarbonImmutable::parse($startsAt, $workspace->timezone);
        $duration = $appointment->starts_at->diffInMinutes($appointment->ends_at);
        $newEnd = $newStart->addMinutes($duration);
        $service = $appointment->serviceModel;
        $connection = $this->ensureSlotAvailable->handle($workspace, $newStart, $newEnd, $service, $appointment);

        $appointment->setAttribute('starts_at', $newStart->utc());
        $appointment->setAttribute('ends_at', $newEnd->utc());
        $this->googleCalendar->updateEvent($connection, $appointment);
        $appointment->save();

        return $appointment->refresh();
    }
}
