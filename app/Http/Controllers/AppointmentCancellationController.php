<?php

namespace App\Http\Controllers;

use App\Actions\CancelAppointment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AppointmentCancellationController extends Controller
{
    public function destroy(Request $request, string $appointment, CancelAppointment $cancelAppointment): RedirectResponse
    {
        $model = $request->user()->workspace?->appointments()->where('public_id', $appointment)->firstOrFail();
        $cancelAppointment->handle($model);

        return back()->with('success', __('messages.appointment_cancelled'));
    }
}
