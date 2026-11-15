<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = $request->user()->workspace;

        return Inertia::render('Services/Index', [
            'hasWorkspace' => $workspace !== null,
            'services' => $workspace?->services()
                ->get()
                ->map(fn ($service): array => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'description' => $service->description,
                    'duration_minutes' => $service->duration_minutes,
                    'price' => $service->price_cents !== null ? number_format($service->price_cents / 100, 2, '.', '') : null,
                    'is_active' => $service->is_active,
                    'sort_order' => $service->sort_order,
                ]) ?? [],
        ]);
    }

    public function store(StoreServiceRequest $request): RedirectResponse
    {
        $workspace = $request->user()->workspace;
        $validated = $request->validated();
        $workspace->services()->create($this->attributes(
            $validated,
            $validated['sort_order'] ?? (($workspace->services()->max('sort_order') ?? 0) + 10),
        ));

        return back()->with('success', __('messages.service_added'));
    }

    public function update(UpdateServiceRequest $request): RedirectResponse
    {
        $service = $request->service();
        $service->update($this->attributes($request->validated(), $request->integer('sort_order', $service->sort_order)));

        return back()->with('success', __('messages.service_updated'));
    }

    public function destroy(Request $request, int $service): RedirectResponse
    {
        $workspace = $request->user()->workspace;
        abort_if($workspace === null, 404);
        $workspace->services()->findOrFail($service)->delete();

        return back()->with('success', __('messages.service_deleted'));
    }

    /**
     * @param  array{name: string, description?: string|null, duration_minutes: int, price?: int|float|string|null, is_active: bool, sort_order?: int|null}  $validated
     * @return array{name: string, description: string|null, duration_minutes: int, price_cents: int|null, is_active: bool, sort_order: int}
     */
    private function attributes(array $validated, int $sortOrder): array
    {
        $price = $validated['price'] ?? null;

        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'duration_minutes' => $validated['duration_minutes'],
            'price_cents' => $price !== null ? (int) round(((float) $price) * 100) : null,
            'is_active' => $validated['is_active'],
            'sort_order' => $sortOrder,
        ];
    }
}
