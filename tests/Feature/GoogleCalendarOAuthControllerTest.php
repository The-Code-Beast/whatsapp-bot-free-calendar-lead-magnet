<?php

namespace Tests\Feature;

use App\Models\CalendarConnection;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleCalendarOAuthControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_connection_requires_saved_workspace_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('google-calendar.redirect'))
            ->assertRedirect(route('workspace.edit'))
            ->assertSessionHas('error', 'Save your business settings before connecting a calendar.');
    }

    public function test_connection_redirects_to_google_with_session_bound_state(): void
    {
        $workspace = Workspace::factory()->create();
        $this->configureGoogle();

        $response = $this->actingAs($workspace->owner)
            ->get(route('google-calendar.redirect'));

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $location);
        $this->assertStringContainsString('access_type=offline', $location);
        $this->assertNotNull(session('google_calendar_oauth_state'));
    }

    public function test_callback_rejects_invalid_oauth_state(): void
    {
        $workspace = Workspace::factory()->create();
        $this->configureGoogle();

        $this->actingAs($workspace->owner)
            ->withSession(['google_calendar_oauth_state' => 'expected-state'])
            ->get(route('google-calendar.callback', ['code' => 'authorization-code', 'state' => 'invalid-state']))
            ->assertStatus(419);

        $this->assertDatabaseCount('calendar_connections', 0);
    }

    public function test_callback_stores_encrypted_tokens_and_selects_primary_calendar(): void
    {
        $workspace = Workspace::factory()->create();
        $this->configureGoogle();
        Http::preventStrayRequests();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'access-token',
                'refresh_token' => 'refresh-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ]),
            'https://www.googleapis.com/calendar/v3/users/me/calendarList*' => Http::response([
                'items' => [
                    ['id' => 'owner@example.com', 'summary' => 'Appointments', 'primary' => true, 'accessRole' => 'owner'],
                ],
            ]),
        ]);

        $this->actingAs($workspace->owner)
            ->withSession(['google_calendar_oauth_state' => 'valid-state'])
            ->get(route('google-calendar.callback', ['code' => 'authorization-code', 'state' => 'valid-state']))
            ->assertRedirect(route('workspace.edit'))
            ->assertSessionHas('success', 'Google Calendar connected.');

        $connection = CalendarConnection::query()->sole();
        $this->assertSame('access-token', $connection->access_token);
        $this->assertSame('refresh-token', $connection->refresh_token);
        $this->assertSame('owner@example.com', $connection->calendar_id);
        $this->assertSame('Appointments', $connection->calendar_name);
        $this->assertNotSame('access-token', $connection->getRawOriginal('access_token'));
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://oauth2.googleapis.com/token');
    }

    public function test_calendar_selection_rejects_calendar_outside_connected_account(): void
    {
        $connection = CalendarConnection::factory()->create();
        $this->configureGoogle();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.googleapis.com/calendar/v3/users/me/calendarList*' => Http::response([
                'items' => [
                    ['id' => 'allowed@example.com', 'summary' => 'Allowed', 'primary' => true, 'accessRole' => 'owner'],
                ],
            ]),
        ]);

        $this->actingAs($connection->workspace->owner)
            ->patch(route('google-calendar.update'), ['calendar_id' => 'other@example.com'])
            ->assertSessionHasErrors([
                'calendar_id' => 'Select a calendar that is available in the connected Google account.',
            ]);

        $this->assertSame('primary', $connection->fresh()->calendar_id);
        Http::assertSentCount(1);
    }

    private function configureGoogle(): void
    {
        config()->set('services.google_calendar', [
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'redirect_uri' => 'http://localhost/integrations/google-calendar/callback',
        ]);
    }
}
