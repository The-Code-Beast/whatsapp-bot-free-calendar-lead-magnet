<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsAppConnection;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WhatsAppConnectionControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get(route('whatsapp.edit'))->assertRedirect(route('login'));
    }

    public function test_user_without_workspace_sees_setup_prerequisite(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('whatsapp.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Settings/WhatsApp')
                ->where('hasWorkspace', false)
                ->where('connection', null));
    }

    public function test_connection_page_does_not_expose_access_token(): void
    {
        $workspace = Workspace::factory()->create();
        WhatsAppConnection::factory()->for($workspace)->create([
            'access_token' => 'top-secret-token',
            'verified_name' => 'Northstar Dental',
        ]);

        $this->actingAs($workspace->owner)
            ->get(route('whatsapp.edit'))
            ->assertOk()
            ->assertDontSee('top-secret-token')
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('connection.verified_name', 'Northstar Dental')
                ->missing('connection.access_token'));
    }

    public function test_valid_meta_credentials_create_encrypted_connection_and_enable_bot(): void
    {
        $this->configureMeta();
        $workspace = Workspace::factory()->create(['bot_enabled' => false]);
        Http::preventStrayRequests();
        Http::fake([
            'https://graph.facebook.com/v23.0/123456789*' => Http::response([
                'id' => '123456789',
                'display_phone_number' => '+1 555-000-1111',
                'verified_name' => 'Northstar Dental',
            ]),
        ]);

        $this->actingAs($workspace->owner)
            ->put(route('whatsapp.update'), [
                'waba_id' => '987654321',
                'phone_number_id' => '123456789',
                'access_token' => 'permanent-meta-token',
                'bot_enabled' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'WhatsApp connected and verified.');

        $this->assertDatabaseHas('whats_app_connections', [
            'workspace_id' => $workspace->id,
            'waba_id' => '987654321',
            'phone_number_id' => '123456789',
            'display_phone_number' => '+1 555-000-1111',
            'verified_name' => 'Northstar Dental',
            'status' => 'connected',
        ]);
        $rawToken = DB::table('whats_app_connections')->value('access_token');
        $this->assertNotSame('permanent-meta-token', $rawToken);
        $this->assertSame('permanent-meta-token', $workspace->whatsAppConnection->access_token);
        $this->assertTrue($workspace->fresh()->bot_enabled);
        Http::assertSentCount(1);
    }

    public function test_invalid_meta_credentials_are_rejected_without_saving_connection(): void
    {
        $this->configureMeta();
        $workspace = Workspace::factory()->create();
        Http::preventStrayRequests();
        Http::fake([
            'https://graph.facebook.com/v23.0/123456789*' => Http::response(['error' => ['message' => 'Invalid token']], 401),
        ]);

        $this->actingAs($workspace->owner)
            ->put(route('whatsapp.update'), [
                'phone_number_id' => '123456789',
                'access_token' => 'invalid-token',
                'bot_enabled' => true,
            ])
            ->assertSessionHasErrors([
                'access_token' => 'Meta could not verify this phone number and access token. Check the credentials and try again.',
            ]);

        $this->assertDatabaseCount('whats_app_connections', 0);
        $this->assertFalse($workspace->fresh()->bot_enabled);
        Http::assertSentCount(1);
    }

    public function test_connection_is_rejected_until_webhook_secrets_are_configured(): void
    {
        config()->set('services.whatsapp.verify_token', null);
        config()->set('services.whatsapp.app_secret', null);
        $workspace = Workspace::factory()->create();
        Http::preventStrayRequests();

        $this->actingAs($workspace->owner)
            ->put(route('whatsapp.update'), [
                'phone_number_id' => '123456789',
                'access_token' => 'token',
                'bot_enabled' => true,
            ])
            ->assertSessionHasErrors([
                'access_token' => 'Configure the Meta app secret and webhook verify token before connecting WhatsApp.',
            ]);

        $this->assertDatabaseCount('whats_app_connections', 0);
        Http::assertNothingSent();
    }

    public function test_request_cannot_override_connection_ownership_or_status(): void
    {
        $this->configureMeta();
        $workspace = Workspace::factory()->create();
        $otherWorkspace = Workspace::factory()->create();
        Http::preventStrayRequests();

        $this->actingAs($workspace->owner)
            ->put(route('whatsapp.update'), [
                'phone_number_id' => '123456789',
                'access_token' => 'token',
                'bot_enabled' => true,
                'workspace_id' => $otherWorkspace->id,
                'status' => 'connected',
            ])
            ->assertSessionHasErrors(['workspace_id', 'status']);

        $this->assertDatabaseCount('whats_app_connections', 0);
        Http::assertNothingSent();
    }

    public function test_existing_connection_can_be_verified_without_resubmitting_token(): void
    {
        $this->configureMeta();
        $workspace = Workspace::factory()->create();
        $connection = WhatsAppConnection::factory()->for($workspace)->create([
            'phone_number_id' => '123456789',
            'access_token' => 'saved-token',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://graph.facebook.com/v23.0/123456789*' => Http::response([
                'id' => '123456789',
                'display_phone_number' => '+1 555-000-2222',
                'verified_name' => 'Updated Business',
            ]),
        ]);

        $this->actingAs($workspace->owner)
            ->put(route('whatsapp.update'), [
                'waba_id' => '',
                'phone_number_id' => '123456789',
                'access_token' => '',
                'bot_enabled' => false,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('saved-token', $connection->fresh()->access_token);
        $this->assertSame('Updated Business', $connection->fresh()->verified_name);
        $this->assertFalse($workspace->fresh()->bot_enabled);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer saved-token'));
    }

    public function test_disconnect_deletes_only_users_connection_and_pauses_bot(): void
    {
        $workspace = Workspace::factory()->create(['bot_enabled' => true]);
        $ownConnection = WhatsAppConnection::factory()->for($workspace)->create();
        $otherConnection = WhatsAppConnection::factory()->create();

        $this->actingAs($workspace->owner)
            ->delete(route('whatsapp.destroy'))
            ->assertSessionHas('success', 'WhatsApp disconnected and automatic replies paused.');

        $this->assertModelMissing($ownConnection);
        $this->assertModelExists($otherConnection);
        $this->assertFalse($workspace->fresh()->bot_enabled);
    }

    private function configureMeta(): void
    {
        config()->set('services.whatsapp.verify_token', 'verify-token');
        config()->set('services.whatsapp.app_secret', 'app-secret');
        config()->set('services.whatsapp.graph_version', 'v23.0');
    }
}
