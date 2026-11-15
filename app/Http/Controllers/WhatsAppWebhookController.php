<?php

namespace App\Http\Controllers;

use App\Actions\HandleWhatsAppMessage;
use App\Models\WhatsAppConnection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $isValid = $request->query('hub_mode') === 'subscribe'
            && filled(config('services.whatsapp.verify_token'))
            && hash_equals((string) config('services.whatsapp.verify_token'), (string) $request->query('hub_verify_token'));

        abort_unless($isValid, 403);

        return response((string) $request->query('hub_challenge'));
    }

    public function handle(Request $request, HandleWhatsAppMessage $handleMessage): Response
    {
        abort_unless($this->hasValidSignature($request), 401);

        if ($request->input('object') !== 'whatsapp_business_account') {
            return response('EVENT_RECEIVED');
        }

        foreach ($request->array('entry') as $entry) {
            foreach (Arr::get($entry, 'changes', []) as $change) {
                $value = Arr::get($change, 'value', []);
                $phoneNumberId = (string) Arr::get($value, 'metadata.phone_number_id', '');
                $connection = WhatsAppConnection::query()
                    ->with('workspace')
                    ->where('phone_number_id', $phoneNumberId)
                    ->where('status', 'connected')
                    ->first();

                if ($connection === null) {
                    continue;
                }

                foreach (Arr::get($value, 'messages', []) as $message) {
                    if (Arr::get($message, 'type') !== 'text') {
                        continue;
                    }

                    $customerWaId = (string) Arr::get($message, 'from', '');
                    $messageId = (string) Arr::get($message, 'id', '');
                    $body = (string) Arr::get($message, 'text.body', '');

                    if ($customerWaId === '' || $messageId === '') {
                        continue;
                    }

                    $handleMessage->handle(
                        $connection,
                        $messageId,
                        $customerWaId,
                        Arr::get($value, 'contacts.0.profile.name'),
                        $body,
                        $message,
                    );
                }
            }
        }

        return response('EVENT_RECEIVED');
    }

    private function hasValidSignature(Request $request): bool
    {
        $appSecret = (string) config('services.whatsapp.app_secret');
        $signature = (string) $request->header('X-Hub-Signature-256');

        if ($appSecret === '' || ! str_starts_with($signature, 'sha256=')) {
            return false;
        }

        return hash_equals('sha256='.hash_hmac('sha256', $request->getContent(), $appSecret), $signature);
    }
}
