<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCustomerRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = $request->user()->workspace;
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->trim()->toString();
        $customers = $workspace?->customers()
            ->withCount(['appointments', 'conversations'])
            ->when($search !== '', fn (Builder $query): Builder => $query->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->when(in_array($status, ['new', 'lead', 'qualified', 'booked', 'completed', 'lost'], true), fn (Builder $query): Builder => $query->where('status', $status))
            ->paginate(15)
            ->withQueryString()
            ->through(fn ($customer): array => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'status' => $customer->status,
                'source' => $customer->source,
                'appointments_count' => $customer->appointments_count,
                'conversations_count' => $customer->conversations_count,
                'last_contact_at' => $customer->last_contact_at?->toIso8601String(),
            ]);
        $selectedCustomer = $workspace?->customers()
            ->with(['appointments' => fn ($query) => $query->latest('starts_at')->limit(10)])
            ->find($request->integer('customer'));

        return Inertia::render('Customers/Index', [
            'hasWorkspace' => $workspace !== null,
            'customers' => $customers,
            'selectedCustomer' => $selectedCustomer ? [
                'id' => $selectedCustomer->id,
                'name' => $selectedCustomer->name,
                'phone' => $selectedCustomer->phone,
                'email' => $selectedCustomer->email,
                'status' => $selectedCustomer->status,
                'source' => $selectedCustomer->source,
                'notes' => $selectedCustomer->notes,
                'last_contact_at' => $selectedCustomer->last_contact_at?->toIso8601String(),
                'appointments' => $selectedCustomer->appointments->map(fn ($appointment): array => [
                    'public_id' => $appointment->public_id,
                    'service' => $appointment->service,
                    'starts_at' => $appointment->starts_at->toIso8601String(),
                    'status' => $appointment->status,
                ])->values(),
            ] : null,
            'filters' => ['search' => $search, 'status' => $status],
            'statusCounts' => $workspace?->customers()
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status') ?? [],
        ]);
    }

    public function update(UpdateCustomerRequest $request): RedirectResponse
    {
        $request->customer()->update($request->safe()->only(['name', 'email', 'status', 'notes']));

        return back()->with('success', __('messages.customer_updated'));
    }
}
