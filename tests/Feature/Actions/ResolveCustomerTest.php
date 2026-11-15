<?php

namespace Tests\Feature\Actions;

use App\Actions\ResolveCustomer;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ResolveCustomerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_resolves_normalized_phone_to_one_workspace_customer(): void
    {
        $workspace = Workspace::factory()->create();
        $action = app(ResolveCustomer::class);

        $first = $action->handle($workspace, '+1 (555) 000-1111', 'Taylor Client');
        $second = $action->handle($workspace, '15550001111', 'Taylor Updated', 'taylor@example.com', status: 'qualified');

        $this->assertSame($first->id, $second->id);
        $this->assertSame('15550001111', $second->phone);
        $this->assertSame('Taylor Updated', $second->name);
        $this->assertSame('taylor@example.com', $second->email);
        $this->assertSame('qualified', $second->status);
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_customer_status_is_not_automatically_downgraded(): void
    {
        $workspace = Workspace::factory()->create();
        $action = app(ResolveCustomer::class);
        $customer = $action->handle($workspace, '15550001111', status: 'booked');

        $resolved = $action->handle($workspace, '15550001111', status: 'new');

        $this->assertSame($customer->id, $resolved->id);
        $this->assertSame('booked', $resolved->status);
    }
}
