<?php

namespace App\Http\Controllers;

use App\Actions\CreateAppointment;
use App\Exceptions\SlotUnavailableException;
use App\Http\Requests\StoreAppointmentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = $request->user()->workspace;
        $appointments = $workspace?->appointments()
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->paginate(12)
            ->through(fn ($appointment): array => [
                'public_id' => $appointment->public_id,
                'customer_name' => $appointment->customer_name,
                'customer_phone' => $appointment->customer_phone,
                'customer_email' => $appointment->customer_email,
                'service' => $appointment->service,
                'starts_at' => $appointment->starts_at->toIso8601String(),
                'ends_at' => $appointment->ends_at->toIso8601String(),
                'timezone' => $appointment->timezone,
                'status' => $appointment->status,
                'notes' => $appointment->notes,
            ]);

        return Inertia::render('Appointments/Index', [
            'appointments' => $appointments,
            'workspace' => $workspace ? [
                'timezone' => $workspace->timezone,
                'duration' => $workspace->appointment_duration_minutes,
                'calendar_connected' => $workspace->calendarConnection?->status === 'connected',
                'now' => now($workspace->timezone)->toIso8601String(),
            ] : null,
        ]);
    }

    public function store(StoreAppointmentRequest $request, CreateAppointment $createAppointment): RedirectResponse
    {
        try {
            $createAppointment->handle(
                $request->user()->workspace,
                $request->safe()->only(['customer_name', 'customer_phone', 'customer_email', 'service', 'starts_at', 'notes']),
                'manual:'.Str::uuid(),
            );
        } catch (SlotUnavailableException $exception) {
            throw ValidationException::withMessages([
                'starts_at' => $exception->getMessage(),
            ]);
        }

        return back()->with('success', __('messages.appointment_booked'));
    }
}
