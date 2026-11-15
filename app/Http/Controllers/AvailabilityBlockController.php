<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAvailabilityBlockRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AvailabilityBlockController extends Controller
{
    public function store(StoreAvailabilityBlockRequest $request): RedirectResponse
    {
        $workspace = $request->user()->workspace;
        $workspace->availabilityBlocks()->create([
            'starts_at' => CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $request->string('starts_at')->toString(), $workspace->timezone)->utc(),
            'ends_at' => CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $request->string('ends_at')->toString(), $workspace->timezone)->utc(),
            'reason' => $request->string('reason')->trim()->toString() ?: null,
        ]);

        return back()->with('success', __('messages.blocked_time_added'));
    }

    public function destroy(Request $request, int $availabilityBlock): RedirectResponse
    {
        $request->user()->workspace?->availabilityBlocks()->findOrFail($availabilityBlock)->delete();

        return back()->with('success', __('messages.blocked_time_removed'));
    }
}
