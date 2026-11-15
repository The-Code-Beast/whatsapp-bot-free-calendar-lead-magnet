<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomerControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get(route('customers.index'))->assertRedirect(route('login'));
    }

    public function test_user_without_workspace_sees_setup_prerequisite(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('customers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Customers/Index')
                ->where('hasWorkspace', false)
                ->where('customers', null));
    }

    public function test_index_searches_and_filters_only_workspace_customers(): void
    {
        $workspace = Workspace::factory()->create();
        $matching = Customer::factory()->for($workspace)->create([
            'name' => 'Avery Johnson',
            'phone' => '15550001111',
            'status' => 'qualified',
        ]);
        Customer::factory()->for($workspace)->create(['name' => 'Taylor Smith', 'status' => 'new']);
        Customer::factory()->create(['name' => 'Avery Other', 'status' => 'qualified']);

        $this->actingAs($workspace->owner)
            ->get(route('customers.index', ['search' => 'Avery', 'status' => 'qualified']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('customers.data', 1)
                ->where('customers.data.0.id', $matching->id)
                ->where('filters.search', 'Avery')
                ->where('filters.status', 'qualified')
                ->where('statusCounts.qualified', 1));
    }

    public function test_detail_includes_recent_booking_history(): void
    {
        $workspace = Workspace::factory()->create();
        $customer = Customer::factory()->for($workspace)->create();
        Appointment::factory()->for($workspace)->for($customer)->create([
            'service' => 'Consultation',
            'starts_at' => '2026-11-05 18:00:00',
        ]);

        $this->actingAs($workspace->owner)
            ->get(route('customers.index', ['customer' => $customer->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('selectedCustomer.id', $customer->id)
                ->has('selectedCustomer.appointments', 1)
                ->where('selectedCustomer.appointments.0.service', 'Consultation'));
    }

    public function test_owner_can_update_customer_profile_status_and_notes(): void
    {
        $workspace = Workspace::factory()->create();
        $customer = Customer::factory()->for($workspace)->create();

        $this->actingAs($workspace->owner)
            ->put(route('customers.update', $customer), [
                'name' => 'Updated Name',
                'email' => 'updated@example.com',
                'status' => 'completed',
                'notes' => 'Prefers afternoon appointments.',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Customer updated.');

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'status' => 'completed',
            'notes' => 'Prefers afternoon appointments.',
        ]);
    }

    public function test_update_hides_other_workspace_customer_and_rejects_protected_fields(): void
    {
        $workspace = Workspace::factory()->create();
        $otherCustomer = Customer::factory()->create();

        $this->actingAs($workspace->owner)
            ->put(route('customers.update', $otherCustomer), [
                'name' => 'Changed',
                'status' => 'lost',
                'phone' => '19999999999',
            ])
            ->assertNotFound();

        $this->assertNotSame('Changed', $otherCustomer->fresh()->name);
    }

    public function test_update_rejects_invalid_status_and_protected_fields(): void
    {
        $workspace = Workspace::factory()->create();
        $customer = Customer::factory()->for($workspace)->create();

        $this->actingAs($workspace->owner)
            ->put(route('customers.update', $customer), [
                'name' => 'Taylor',
                'status' => 'admin',
                'phone' => '19999999999',
                'workspace_id' => 999,
                'source' => 'injected',
            ])
            ->assertSessionHasErrors(['status', 'phone', 'workspace_id', 'source']);

        $this->assertSame('new', $customer->fresh()->status);
    }
}
