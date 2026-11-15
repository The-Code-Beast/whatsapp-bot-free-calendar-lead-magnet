<?php

namespace App\Services;

use App\Models\WhatsAppConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class WhatsAppClient
{
    /** @return array{id: string, display_phone_number?: string, verified_name?: string} */
    public function phoneNumberDetails(string $phoneNumberId, string $accessToken): array
    {
        $response = $this->request($accessToken)
            ->withQueryParameters(['fields' => 'id,display_phone_number,verified_name'])
            ->get("/{$phoneNumberId}")
            ->throw();

        return $response->json();
    }

    public function sendText(WhatsAppConnection $connection, string $recipient, string $body): string
    {
        $response = $this->request($connection->access_token)
            ->post("/{$connection->phone_number_id}/messages", [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $recipient,
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $body,
                ],
            ])
            ->throw();

        return (string) $response->json('messages.0.id');
    }

    private function request(string $accessToken): PendingRequest
    {
        $version = config('services.whatsapp.graph_version');

        return Http::baseUrl("https://graph.facebook.com/{$version}")
            ->withToken($accessToken)
            ->acceptJson()
            ->timeout(10)
            ->connectTimeout(3);
    }
}
