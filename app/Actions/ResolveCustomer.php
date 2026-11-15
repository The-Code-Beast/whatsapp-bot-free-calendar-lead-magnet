<?php

namespace App\Actions;

use App\Models\Customer;
use App\Models\Workspace;

class ResolveCustomer
{
    public function handle(
        Workspace $workspace,
        string $phone,
        ?string $name = null,
        ?string $email = null,
        string $source = 'whatsapp',
        string $status = 'new',
    ): Customer {
        $normalizedPhone = preg_replace('/\D+/', '', $phone) ?: $phone;
        $customer = $workspace->customers()->firstOrNew(['phone' => $normalizedPhone]);

        if (filled($name)) {
            $customer->name = $name;
        }

        if (filled($email)) {
            $customer->email = $email;
        }

        if (! $customer->exists) {
            $customer->source = $source;
        }

        if ($this->statusRank($status) >= $this->statusRank($customer->status ?? 'new')) {
            $customer->status = $status;
        }

        $customer->last_contact_at = now();
        $customer->save();

        return $customer;
    }

    private function statusRank(string $status): int
    {
        return match ($status) {
            'lead' => 1,
            'qualified' => 2,
            'booked' => 3,
            'completed' => 4,
            'lost' => 5,
            default => 0,
        };
    }
}
