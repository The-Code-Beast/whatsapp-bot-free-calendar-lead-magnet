<?php

namespace App\Actions;

use App\Exceptions\SlotUnavailableException;
use App\Models\Service;
use App\Models\Workspace;
use Carbon\CarbonImmutable;

class FindAvailableSlots
{
    public function __construct(private EnsureAppointmentSlotAvailable $ensureSlotAvailable) {}

    /** @return list<CarbonImmutable> */
    public function handle(Workspace $workspace, Service $service, ?CarbonImmutable $after = null, int $limit = 3): array
    {
        $cursor = ($after ?? CarbonImmutable::now($workspace->timezone))
            ->setTimezone($workspace->timezone)
            ->addMinutes(30)
            ->startOfMinute();
        $cursor = $cursor->minute <= 30
            ? $cursor->setMinute(30)
            : $cursor->addHour()->setMinute(0);
        $deadline = $cursor->addDays(14);
        $slots = [];
        $googleChecks = 0;

        while ($cursor->lessThan($deadline) && count($slots) < $limit && $googleChecks < 30) {
            $endsAt = $cursor->addMinutes($service->duration_minutes);

            try {
                $this->ensureSlotAvailable->handle($workspace, $cursor, $endsAt, $service, checkGoogle: false);
                $googleChecks++;
                $this->ensureSlotAvailable->handle($workspace, $cursor, $endsAt, $service);
                $slots[] = $cursor;
            } catch (SlotUnavailableException) {
                // Continue scanning for the next valid slot.
            }

            $cursor = $cursor->addMinutes(30);
        }

        return $slots;
    }
}
