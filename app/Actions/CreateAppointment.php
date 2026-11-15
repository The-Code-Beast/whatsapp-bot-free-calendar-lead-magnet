<?php

namespace App\Actions;

use App\Exceptions\SlotUnavailableException;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\Workspace;
use App\Services\GoogleCalendarClient;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Throwable;

class CreateAppointment
{
    public function __construct(
        private GoogleCalendarClient $googleCalendar,
        private EnsureAppointmentSlotAvailable $ensureSlotAvailable,
        private ResolveCustomer $resolveCustomer,
    ) {}

    /**
     * @param  array{customer_name: string, customer_phone: string, customer_email?: string|null, conversation_id?: int, service_id?: int, service?: string|null, duration_minutes?: int, starts_at: string, notes?: string|null}  $attributes
     */
    public function handle(Workspace $workspace, array $attributes, string $idempotencyKey): Appointment
    {
        $existingAppointment = $workspace->appointments()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingAppointment !== null) {
            return $existingAppointment;
        }

        $service = $this->resolveService($workspace, $attributes['service_id'] ?? null);
        $customer = $this->resolveCustomer->handle(
            $workspace,
            $attributes['customer_phone'],
            $attributes['customer_name'],
            $attributes['customer_email'] ?? null,
            isset($attributes['conversation_id']) ? 'whatsapp' : 'appointment',
            'lead',
        );
        $startsAt = CarbonImmutable::parse($attributes['starts_at'], $workspace->timezone);
        $endsAt = $startsAt->addMinutes($service?->duration_minutes ?? $attributes['duration_minutes'] ?? $workspace->appointment_duration_minutes);

        $lockKey = 'booking-slot:'.$workspace->id.':'.hash('sha256', $startsAt->utc()->toIso8601String().$endsAt->utc()->toIso8601String());

        try {
            return Cache::lock($lockKey, 30)->block(5, function () use ($workspace, $attributes, $idempotencyKey, $startsAt, $endsAt, $service, $customer): Appointment {
                $existingAppointment = $workspace->appointments()
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existingAppointment !== null) {
                    return $existingAppointment;
                }

                $connection = $this->ensureSlotAvailable->handle($workspace, $startsAt, $endsAt, $service);

                $appointment = $workspace->appointments()->create([
                    'calendar_connection_id' => $connection->id,
                    'customer_id' => $customer->id,
                    'conversation_id' => $attributes['conversation_id'] ?? null,
                    'service_id' => $service?->id,
                    'customer_name' => $attributes['customer_name'],
                    'customer_phone' => $attributes['customer_phone'],
                    'customer_email' => $attributes['customer_email'] ?? null,
                    'service' => $attributes['service'] ?? null,
                    'starts_at' => $startsAt->utc(),
                    'ends_at' => $endsAt->utc(),
                    'timezone' => $workspace->timezone,
                    'status' => 'pending',
                    'idempotency_key' => $idempotencyKey,
                    'notes' => $attributes['notes'] ?? null,
                ]);

                try {
                    $appointment->update([
                        'google_event_id' => $this->googleCalendar->createEvent($connection, $appointment),
                        'status' => 'confirmed',
                    ]);
                } catch (Throwable $exception) {
                    $appointment->delete();

                    throw $exception;
                }

                $customer->update(['status' => 'booked', 'last_contact_at' => now()]);

                return $appointment->refresh();
            });
        } catch (LockTimeoutException) {
            throw new SlotUnavailableException;
        }
    }

    private function resolveService(Workspace $workspace, ?int $serviceId): ?Service
    {
        if ($serviceId === null) {
            return null;
        }

        $service = $workspace->services()->whereKey($serviceId)->where('is_active', true)->first();

        if ($service === null) {
            throw new SlotUnavailableException('That service is no longer available. Please choose another service.');
        }

        return $service;
    }
}
