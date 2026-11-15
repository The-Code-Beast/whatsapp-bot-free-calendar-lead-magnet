<?php

namespace App\Http\Controllers;

use App\Actions\RescheduleAppointment;
use App\Exceptions\SlotUnavailableException;
use App\Http\Requests\RescheduleAppointmentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class AppointmentRescheduleController extends Controller
{
    public function update(RescheduleAppointmentRequest $request, RescheduleAppointment $rescheduleAppointment): RedirectResponse
    {
        try {
            $rescheduleAppointment->handle($request->appointment(), $request->string('starts_at')->toString());
        } catch (SlotUnavailableException $exception) {
            throw ValidationException::withMessages(['starts_at' => $exception->getMessage()]);
        }

        return back()->with('success', __('messages.appointment_rescheduled'));
    }
}
