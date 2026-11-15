<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $workspace = $request->user()->workspace;
        $range = in_array($request->integer('range'), [7, 30, 90], true) ? $request->integer('range') : 30;

        if ($workspace === null) {
            return Inertia::render('Analytics/Index', [
                'hasWorkspace' => false,
                'range' => $range,
                'metrics' => null,
                'dailyVolume' => [],
                'services' => [],
                'pipeline' => [],
            ]);
        }

        $localEnd = CarbonImmutable::now($workspace->timezone)->endOfDay();
        $localStart = $localEnd->subDays($range - 1)->startOfDay();
        $appointments = $workspace->appointments()
            ->with('serviceModel:id,name,price_cents')
            ->whereBetween('starts_at', [$localStart->utc(), $localEnd->utc()])
            ->get(['id', 'service_id', 'service', 'starts_at', 'status']);
        $confirmed = $appointments->where('status', 'confirmed');
        $cancelled = $appointments->where('status', 'cancelled');
        $decided = $confirmed->count() + $cancelled->count();
        $pipeline = $workspace->customers()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count): int => (int) $count);
        $convertedCustomers = collect(['booked', 'completed'])->sum(fn (string $status): int => $pipeline->get($status, 0));
        $customerCount = $pipeline->sum();

        return Inertia::render('Analytics/Index', [
            'hasWorkspace' => true,
            'range' => $range,
            'timezone' => $workspace->timezone,
            'metrics' => [
                'appointments' => $appointments->count(),
                'confirmed' => $confirmed->count(),
                'cancelled' => $cancelled->count(),
                'cancellation_rate' => $decided > 0 ? round(($cancelled->count() / $decided) * 100, 1) : 0,
                'lead_conversion_rate' => $customerCount > 0 ? round(($convertedCustomers / $customerCount) * 100, 1) : 0,
                'estimated_revenue_cents' => $confirmed->sum(fn (Appointment $appointment): int => $appointment->serviceModel?->price_cents ?? 0),
                'reminders_sent' => $workspace->appointments()->whereBetween('reminder_sent_at', [$localStart->utc(), $localEnd->utc()])->count(),
                'followups_sent' => $workspace->appointments()->whereBetween('followup_sent_at', [$localStart->utc(), $localEnd->utc()])->count(),
            ],
            'dailyVolume' => $this->dailyVolume($appointments, $localStart, $range, $workspace->timezone),
            'services' => $this->servicePerformance($appointments),
            'pipeline' => collect(['new', 'lead', 'qualified', 'booked', 'completed', 'lost'])
                ->map(fn (string $status): array => ['status' => $status, 'count' => $pipeline->get($status, 0)]),
        ]);
    }

    /**
     * @param  Collection<int, Appointment>  $appointments
     * @return Collection<int, array{date: string, label: string, confirmed: int, cancelled: int}>
     */
    private function dailyVolume(Collection $appointments, CarbonImmutable $start, int $range, string $timezone): Collection
    {
        $grouped = $appointments->groupBy(fn (Appointment $appointment): string => $appointment->starts_at->setTimezone($timezone)->format('Y-m-d'));

        return collect(range(0, $range - 1))->map(function (int $offset) use ($grouped, $start): array {
            $date = $start->addDays($offset);
            $appointmentsForDay = $grouped->get($date->format('Y-m-d'), collect());

            return [
                'date' => $date->format('Y-m-d'),
                'label' => $date->format('M j'),
                'confirmed' => $appointmentsForDay->where('status', 'confirmed')->count(),
                'cancelled' => $appointmentsForDay->where('status', 'cancelled')->count(),
            ];
        });
    }

    /**
     * @param  Collection<int, Appointment>  $appointments
     * @return Collection<int, array{name: string, appointments: int, confirmed: int, revenue_cents: int}>
     */
    private function servicePerformance(Collection $appointments): Collection
    {
        return $appointments
            ->groupBy(fn (Appointment $appointment): string => $appointment->serviceModel?->name ?? $appointment->service ?? 'Unspecified')
            ->map(function (Collection $serviceAppointments, string $name): array {
                $confirmed = $serviceAppointments->where('status', 'confirmed');

                return [
                    'name' => $name,
                    'appointments' => $serviceAppointments->count(),
                    'confirmed' => $confirmed->count(),
                    'revenue_cents' => $confirmed->sum(fn (Appointment $appointment): int => $appointment->serviceModel?->price_cents ?? 0),
                ];
            })
            ->sortByDesc('appointments')
            ->values();
    }
}
