<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ServiceControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get(route('services.index'))->assertRedirect(route('login'));
    }

    public function test_user_without_workspace_sees_setup_prerequisite(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('services.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Services/Index')
                ->where('hasWorkspace', false)
                ->has('services', 0));
    }

    public function test_index_lists_only_workspace_services_in_configured_order(): void
    {
        $workspace = Workspace::factory()->create();
        Service::factory()->for($workspace)->create(['name' => 'Second', 'sort_order' => 20]);
        Service::factory()->for($workspace)->create(['name' => 'First', 'sort_order' => 10, 'price_cents' => 12550]);
        Service::factory()->create(['name' => 'Other Workspace']);

        $this->actingAs($workspace->owner)
            ->get(route('services.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('services', 2)
                ->where('services.0.name', 'First')
                ->where('services.0.price', '125.50')
                ->where('services.1.name', 'Second'));
    }

    public function test_valid_request_creates_service_with_minor_unit_price_and_default_order(): void
    {
        $workspace = Workspace::factory()->create();
        Service::factory()->for($workspace)->create(['sort_order' => 20]);

        $this->actingAs($workspace->owner)
            ->post(route('services.store'), $this->validPayload())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Service added.');

        $this->assertDatabaseHas('services', [
            'workspace_id' => $workspace->id,
            'name' => 'Initial consultation',
            'duration_minutes' => 45,
            'price_cents' => 12999,
            'is_active' => true,
            'sort_order' => 30,
        ]);
    }

    public function test_success_message_uses_the_selected_spanish_locale(): void
    {
        $workspace = Workspace::factory()->create();

        $this->actingAs($workspace->owner)
            ->withUnencryptedCookie('bookline_language', 'es')
            ->post(route('services.store'), $this->validPayload())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Servicio agregado.');
    }

    public function test_service_name_must_be_unique_inside_workspace(): void
    {
        $workspace = Workspace::factory()->create();
        Service::factory()->for($workspace)->create(['name' => 'Initial consultation']);
        Service::factory()->create(['name' => 'Initial consultation']);

        $this->actingAs($workspace->owner)
            ->post(route('services.store'), $this->validPayload())
            ->assertSessionHasErrors('name');

        $this->assertSame(1, $workspace->services()->count());
    }

    public function test_request_rejects_invalid_duration_price_and_protected_fields(): void
    {
        $workspace = Workspace::factory()->create();
        $payload = $this->validPayload();
        $payload['duration_minutes'] = 2;
        $payload['price'] = '12.999';
        $payload['workspace_id'] = 999;
        $payload['price_cents'] = 1;

        $this->actingAs($workspace->owner)
            ->post(route('services.store'), $payload)
            ->assertSessionHasErrors(['duration_minutes', 'price', 'workspace_id', 'price_cents']);

        $this->assertDatabaseCount('services', 0);
    }

    public function test_user_can_update_own_service_and_remove_price(): void
    {
        $workspace = Workspace::factory()->create();
        $service = Service::factory()->for($workspace)->create(['price_cents' => 5000]);
        $payload = $this->validPayload();
        $payload['name'] = 'Follow-up visit';
        $payload['price'] = null;
        $payload['is_active'] = false;
        $payload['sort_order'] = 5;

        $this->actingAs($workspace->owner)
            ->put(route('services.update', $service), $payload)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Service updated.');

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'workspace_id' => $workspace->id,
            'name' => 'Follow-up visit',
            'price_cents' => null,
            'is_active' => false,
            'sort_order' => 5,
        ]);
    }

    public function test_update_and_delete_return_not_found_for_other_workspace_service(): void
    {
        $workspace = Workspace::factory()->create();
        $otherService = Service::factory()->create();

        $this->actingAs($workspace->owner)
            ->put(route('services.update', $otherService), $this->validPayload())
            ->assertNotFound();
        $this->actingAs($workspace->owner)
            ->delete(route('services.destroy', $otherService))
            ->assertNotFound();

        $this->assertModelExists($otherService);
    }

    public function test_user_can_delete_own_service(): void
    {
        $workspace = Workspace::factory()->create();
        $service = Service::factory()->for($workspace)->create();

        $this->actingAs($workspace->owner)
            ->delete(route('services.destroy', $service))
            ->assertSessionHas('success', 'Service deleted.');

        $this->assertModelMissing($service);
    }

    /** @return array<string, mixed> */
    private function validPayload(): array
    {
        return [
            'name' => 'Initial consultation',
            'description' => 'A complete first visit.',
            'duration_minutes' => 45,
            'price' => '129.99',
            'is_active' => true,
            'sort_order' => null,
        ];
    }
}
