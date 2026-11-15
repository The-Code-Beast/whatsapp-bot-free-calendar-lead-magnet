<?php

namespace App\Actions;

use App\Models\Appointment;
use App\Services\GoogleCalendarClient;

class CancelAppointment
{
    public function __construct(private GoogleCalendarClient $googleCalendar) {}

    public function handle(Appointment $appointment): Appointment
    {
        if ($appointment->status === 'cancelled') {
            return $appointment;
        }

        if (filled($appointment->google_event_id) && $appointment->calendarConnection !== null) {
            $this->googleCalendar->deleteEvent($appointment->calendarConnection, $appointment);
        }

        $appointment->update(['status' => 'cancelled']);

        return $appointment->refresh();
    }
}
