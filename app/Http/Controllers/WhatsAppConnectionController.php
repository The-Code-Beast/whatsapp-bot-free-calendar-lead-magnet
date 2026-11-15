<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWhatsAppConnectionRequest;
use App\Services\WhatsAppClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppConnectionController extends Controller
{
    public function edit(Request $request): Response
    {
        $workspace = $request->user()->workspace;
        $connection = $workspace?->whatsAppConnection;

        return Inertia::render('Settings/WhatsApp', [
            'hasWorkspace' => $workspace !== null,
            'connection' => $connection ? [
                'waba_id' => $connection->waba_id,
                'phone_number_id' => $connection->phone_number_id,
                'display_phone_number' => $connection->display_phone_number,
                'verified_name' => $connection->verified_name,
                'status' => $connection->status,
                'connected_at' => $connection->connected_at?->toIso8601String(),
            ] : null,
            'botEnabled' => $workspace?->bot_enabled ?? false,
            'webhookUrl' => route('webhooks.whatsapp.handle'),
            'verifyTokenConfigured' => filled(config('services.whatsapp.verify_token')),
            'appSecretConfigured' => filled(config('services.whatsapp.app_secret')),
        ]);
    }

    public function update(StoreWhatsAppConnectionRequest $request, WhatsAppClient $whatsApp): RedirectResponse
    {
        $workspace = $request->user()->workspace;
        $existingConnection = $workspace->whatsAppConnection;
        $submittedAccessToken = $request->string('access_token')->trim()->toString();
        $accessToken = $submittedAccessToken !== '' ? $submittedAccessToken : $existingConnection?->access_token;

        try {
            $details = $whatsApp->phoneNumberDetails(
                $request->string('phone_number_id')->toString(),
                (string) $accessToken,
            );
        } catch (ConnectionException|RequestException) {
            throw ValidationException::withMessages([
                'access_token' => 'Meta could not verify this phone number and access token. Check the credentials and try again.',
            ]);
        }

        if (($details['id'] ?? null) !== $request->string('phone_number_id')->toString()) {
            throw ValidationException::withMessages([
                'phone_number_id' => 'Meta returned a different phone number. Check the Phone Number ID and try again.',
            ]);
        }

        DB::transaction(function () use ($request, $workspace, $accessToken, $details): void {
            $workspace->whatsAppConnection()->updateOrCreate([], [
                'waba_id' => $request->string('waba_id')->trim()->toString() ?: null,
                'phone_number_id' => $details['id'],
                'display_phone_number' => $details['display_phone_number'] ?? null,
                'verified_name' => $details['verified_name'] ?? null,
                'status' => 'connected',
                'access_token' => $accessToken,
                'connected_at' => now(),
            ]);
            $workspace->update(['bot_enabled' => $request->boolean('bot_enabled')]);
        });

        return back()->with('success', __('messages.whatsapp_connected'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $workspace = $request->user()->workspace;
        $workspace?->whatsAppConnection?->delete();
        $workspace?->update(['bot_enabled' => false]);

        return back()->with('success', __('messages.whatsapp_disconnected'));
    }
}
