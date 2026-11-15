<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAvailabilityRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AvailabilityController extends Controller
{
    public function edit(Request $request): Response
    {
        $workspace = $request->user()->workspace?->load(['businessHours', 'availabilityBlocks', 'services.businessHours']);

        return Inertia::render('Availability/Edit', [
            'workspace' => $workspace ? [
                'timezone' => $workspace->timezone,
                'buffer_minutes' => $workspace->buffer_minutes,
                'business_hours' => $workspace->businessHours->map->only(['day_of_week', 'opens_at', 'closes_at', 'is_enabled'])->values(),
            ] : null,
            'blocks' => $workspace?->availabilityBlocks->map(fn ($block): array => [
                'id' => $block->id,
                'starts_at' => $block->starts_at->setTimezone($workspace->timezone)->format('Y-m-d\TH:i'),
                'ends_at' => $block->ends_at->setTimezone($workspace->timezone)->format('Y-m-d\TH:i'),
                'reason' => $block->reason,
            ])->values() ?? [],
            'services' => $workspace?->services->map(fn ($service): array => [
                'id' => $service->id,
                'name' => $service->name,
                'uses_custom_hours' => $service->uses_custom_hours,
                'business_hours' => $service->businessHours->map->only(['day_of_week', 'opens_at', 'closes_at', 'is_enabled'])->values(),
            ])->values() ?? [],
        ]);
    }

    public function update(UpdateAvailabilityRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $workspace = $request->user()->workspace;
            $workspace->update(['buffer_minutes' => $request->integer('buffer_minutes')]);

            foreach ($request->validated('business_hours') as $hours) {
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

        return back()->with('success', __('messages.availability_saved'));
    }
}
