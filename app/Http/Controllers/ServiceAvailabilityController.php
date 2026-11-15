<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateServiceAvailabilityRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ServiceAvailabilityController extends Controller
{
    public function update(UpdateServiceAvailabilityRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $service = $request->service();
            $service->update(['uses_custom_hours' => $request->boolean('uses_custom_hours')]);

            foreach ($request->validated('business_hours') as $hours) {
                $service->businessHours()->updateOrCreate(
                    ['day_of_week' => $hours['day_of_week']],
                    [
                        'is_enabled' => $hours['is_enabled'],
                        'opens_at' => $hours['is_enabled'] ? $hours['opens_at'] : null,
                        'closes_at' => $hours['is_enabled'] ? $hours['closes_at'] : null,
                    ],
                );
            }
        });

        return back()->with('success', __('messages.service_availability_saved'));
    }
}
