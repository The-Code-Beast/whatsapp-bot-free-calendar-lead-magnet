<?php

namespace App\Actions;

use App\Exceptions\SlotUnavailableException;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Service;
use App\Models\WhatsAppConnection;
use App\Services\WhatsAppClient;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Throwable;

class HandleWhatsAppMessage
{
    public function __construct(
        private CreateAppointment $createAppointment,
        private FindAvailableSlots $findAvailableSlots,
        private RescheduleAppointment $rescheduleAppointment,
        private CancelAppointment $cancelAppointment,
        private ResolveCustomer $resolveCustomer,
        private WhatsAppClient $whatsApp,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(
        WhatsAppConnection $connection,
        string $messageId,
        string $customerWaId,
        ?string $customerName,
        string $body,
        array $payload,
    ): void {
        $workspace = $connection->workspace;
        $customer = $this->resolveCustomer->handle($workspace, $customerWaId, $customerName);
        $conversation = $workspace->conversations()->firstOrCreate(
            ['customer_wa_id' => $customerWaId],
            [
                'whats_app_connection_id' => $connection->id,
                'customer_id' => $customer->id,
                'customer_name' => $customerName,
                'status' => 'active',
                'booking_stage' => 'collecting',
            ],
        );

        $incomingMessage = Message::query()->firstOrCreate(
            ['meta_message_id' => $messageId],
            [
                'conversation_id' => $conversation->id,
                'direction' => 'inbound',
                'sender_type' => 'customer',
                'type' => 'text',
                'body' => $body,
                'payload' => $payload,
                'status' => 'received',
                'sent_at' => now(),
            ],
        );

        if ($incomingMessage->status === 'processed') {
            return;
        }

        $conversation->update([
            'customer_id' => $customer->id,
            'whats_app_connection_id' => $connection->id,
            'customer_name' => $conversation->customer_name ?? $customerName,
            'last_message_at' => now(),
        ]);
        $this->ensureLanguage($conversation, $body);

        if (! $workspace->bot_enabled || $conversation->human_takeover) {
            $incomingMessage->update(['status' => 'processed']);

            return;
        }

        match ($conversation->booking_stage) {
            'awaiting_service' => $this->collectService($conversation, $incomingMessage, $connection, $body),
            'awaiting_datetime' => $this->collectDateTime($conversation, $incomingMessage, $connection, $body),
            'awaiting_reschedule_datetime' => $this->collectRescheduleDateTime($conversation, $incomingMessage, $connection, $body),
            'completed' => $this->handleBookedConversation($conversation, $incomingMessage, $connection, $body),
            default => $this->startBooking($conversation, $incomingMessage, $connection),
        };
    }

    private function startBooking(Conversation $conversation, Message $incomingMessage, WhatsAppConnection $connection): void
    {
        $services = $this->availableServices($conversation);

        if ($services->isEmpty()) {
            $this->reply($conversation, $incomingMessage, $connection, $this->message($conversation, 'online_unavailable'));

            return;
        }

        $name = $conversation->customer_name ? ' '.$conversation->customer_name : '';
        $serviceList = $services
            ->values()
            ->map(fn ($service, int $index): string => ($index + 1).". {$service->name} ({$service->duration_minutes} min)")
            ->implode("\n");
        $this->reply($conversation, $incomingMessage, $connection, $this->message($conversation, 'start', [
            'name' => $name,
            'services' => $serviceList,
        ]));
        $conversation->update(['booking_stage' => 'awaiting_service']);
    }

    private function collectService(Conversation $conversation, Message $incomingMessage, WhatsAppConnection $connection, string $body): void
    {
        $service = trim($body);
        $services = $this->availableServices($conversation)->values();
        $matchedService = ctype_digit($service)
            ? $services->get(((int) $service) - 1)
            : $services->first(fn ($availableService): bool => Str::lower($availableService->name) === Str::lower($service));

        if ($matchedService === null) {
            $serviceList = $services
                ->map(fn ($availableService, int $index): string => ($index + 1).'. '.$availableService->name)
                ->implode("\n");
            $this->reply($conversation, $incomingMessage, $connection, $this->message($conversation, 'invalid_service', [
                'services' => $serviceList,
            ]));

            return;
        }

        $suggestions = $this->findAvailableSlots->handle($conversation->workspace, $matchedService);
        $suggestionText = $this->numberedSlotList($suggestions, $conversation);
        $prompt = $suggestions === []
            ? $this->message($conversation, 'ask_datetime')
            : $this->message($conversation, 'choose_time', ['slots' => $suggestionText]);
        $this->reply($conversation, $incomingMessage, $connection, $prompt);
        $conversation->update([
            'booking_stage' => 'awaiting_datetime',
            'booking_data' => array_merge($conversation->booking_data ?? [], [
                'service_id' => $matchedService->id,
                'service' => $matchedService->name,
                'duration_minutes' => $matchedService->duration_minutes,
                'suggested_slots' => $this->slotValues($suggestions),
            ]),
        ]);
    }

    private function collectDateTime(Conversation $conversation, Message $incomingMessage, WhatsAppConnection $connection, string $body): void
    {
        $startsAt = CarbonImmutable::createFromFormat('!Y-m-d H:i', $this->resolveSlotReply($conversation, $body), $conversation->workspace->timezone);

        if ($startsAt === false || CarbonImmutable::getLastErrors() !== false) {
            $this->reply($conversation, $incomingMessage, $connection, $this->message($conversation, 'invalid_time'));

            return;
        }

        try {
            $appointment = $this->createAppointment->handle($conversation->workspace, [
                'customer_name' => $conversation->customer_name ?? $this->message($conversation, 'whatsapp_customer'),
                'customer_phone' => $conversation->customer_wa_id,
                'conversation_id' => $conversation->id,
                'service_id' => $conversation->booking_data['service_id'] ?? null,
                'service' => $conversation->booking_data['service'] ?? $this->message($conversation, 'appointment'),
                'duration_minutes' => $conversation->booking_data['duration_minutes'] ?? $conversation->workspace->appointment_duration_minutes,
                'starts_at' => $startsAt->format('Y-m-d H:i'),
                'notes' => $this->message($conversation, 'booked_notes'),
            ], $incomingMessage->meta_message_id);
        } catch (SlotUnavailableException $exception) {
            $service = $conversation->workspace->services()->find($conversation->booking_data['service_id'] ?? null);
            $suggestions = $service ? $this->findAvailableSlots->handle($conversation->workspace, $service, $startsAt) : [];
            $reason = $this->language($conversation) === 'es'
                ? $this->message($conversation, 'generic_slot_unavailable')
                : $exception->getMessage();
            $reply = $suggestions === []
                ? $this->message($conversation, 'type_alternative', ['reason' => $reason])
                : $this->message($conversation, 'choose_alternative', [
                    'reason' => $reason,
                    'slots' => $this->numberedSlotList($suggestions, $conversation),
                ]);
            $this->reply($conversation, $incomingMessage, $connection, $reply);
            $conversation->update([
                'booking_data' => array_merge($conversation->booking_data ?? [], [
                    'suggested_slots' => $this->slotValues($suggestions),
                ]),
            ]);

            return;
        }

        $localStart = $appointment->starts_at->setTimezone($conversation->workspace->timezone);
        $this->reply($conversation, $incomingMessage, $connection, $this->message($conversation, 'booked', [
            'date' => $this->formatDate($conversation, $localStart),
            'time' => $localStart->format('g:i A'),
        ]));
        $conversation->update([
            'status' => 'booked',
            'booking_stage' => 'completed',
            'booking_data' => array_merge($conversation->booking_data ?? [], ['appointment_id' => $appointment->id]),
        ]);
    }

    private function handleBookedConversation(Conversation $conversation, Message $incomingMessage, WhatsAppConnection $connection, string $body): void
    {
        $command = Str::lower(trim($body));
        $appointment = $conversation->workspace->appointments()->find($conversation->booking_data['appointment_id'] ?? null);

        if ($appointment === null || $appointment->status !== 'confirmed') {
            $this->reply($conversation, $incomingMessage, $connection, $this->message($conversation, 'active_not_found'));

            return;
        }

        if (in_array($command, ['cancel', 'cancel appointment', 'cancelar', 'cancelar cita'], true)) {
            $this->cancelAppointment->handle($appointment);
            $this->reply($conversation, $incomingMessage, $connection, $this->message($conversation, 'cancelled'));
            $conversation->update(['status' => 'cancelled']);

            return;
        }

        if (in_array($command, ['reschedule', 'reschedule appointment', 'reprogramar', 'reprogramar cita'], true)) {
            $suggestions = $appointment->serviceModel
                ? $this->findAvailableSlots->handle($conversation->workspace, $appointment->serviceModel)
                : [];
            $suggestionText = $this->numberedSlotList($suggestions, $conversation);
            $prompt = $suggestions === []
                ? $this->message($conversation, 'ask_reschedule')
                : $this->message($conversation, 'choose_reschedule', ['slots' => $suggestionText]);
            $this->reply($conversation, $incomingMessage, $connection, $prompt);
            $conversation->update([
                'booking_stage' => 'awaiting_reschedule_datetime',
                'booking_data' => array_merge($conversation->booking_data ?? [], [
                    'suggested_slots' => $this->slotValues($suggestions),
                ]),
            ]);

            return;
        }

        $this->reply($conversation, $incomingMessage, $connection, $this->message($conversation, 'booked_help'));
    }

    private function collectRescheduleDateTime(Conversation $conversation, Message $incomingMessage, WhatsAppConnection $connection, string $body): void
    {
        $startsAt = CarbonImmutable::createFromFormat('!Y-m-d H:i', $this->resolveSlotReply($conversation, $body), $conversation->workspace->timezone);

        if ($startsAt === false || CarbonImmutable::getLastErrors() !== false) {
            $this->reply($conversation, $incomingMessage, $connection, $this->message($conversation, 'invalid_reschedule_time'));

            return;
        }

        $appointment = $conversation->workspace->appointments()->find($conversation->booking_data['appointment_id'] ?? null);

        if ($appointment === null) {
            $this->reply($conversation, $incomingMessage, $connection, $this->message($conversation, 'appointment_not_found'));

            return;
        }

        try {
            $appointment = $this->rescheduleAppointment->handle($appointment, $startsAt->format('Y-m-d H:i'));
        } catch (SlotUnavailableException $exception) {
            $suggestions = $appointment->serviceModel
                ? $this->findAvailableSlots->handle($conversation->workspace, $appointment->serviceModel, $startsAt)
                : [];
            $reason = $this->language($conversation) === 'es'
                ? $this->message($conversation, 'generic_slot_unavailable')
                : $exception->getMessage();
            $reply = $suggestions === []
                ? $this->message($conversation, 'type_alternative', ['reason' => $reason])
                : $this->message($conversation, 'choose_alternative', [
                    'reason' => $reason,
                    'slots' => $this->numberedSlotList($suggestions, $conversation),
                ]);
            $this->reply($conversation, $incomingMessage, $connection, $reply);
            $conversation->update([
                'booking_data' => array_merge($conversation->booking_data ?? [], [
                    'suggested_slots' => $this->slotValues($suggestions),
                ]),
            ]);

            return;
        }

        $localStart = $appointment->starts_at->setTimezone($conversation->workspace->timezone);
        $this->reply($conversation, $incomingMessage, $connection, $this->message($conversation, 'moved', [
            'date' => $this->formatDate($conversation, $localStart),
            'time' => $localStart->format('g:i A'),
        ]));
        $conversation->update(['booking_stage' => 'completed']);
    }

    private function ensureLanguage(Conversation $conversation, string $body): void
    {
        $configuredLanguage = $conversation->workspace->assistant_language;
        $language = in_array($configuredLanguage, ['en', 'es'], true)
            ? $configuredLanguage
            : ($conversation->booking_data['language'] ?? $this->detectLanguage($body));

        if (($conversation->booking_data['language'] ?? null) === $language) {
            return;
        }

        $conversation->update([
            'booking_data' => array_merge($conversation->booking_data ?? [], ['language' => $language]),
        ]);
    }

    private function detectLanguage(string $body): string
    {
        $normalized = Str::lower($body);

        if (preg_match('/[áéíóúüñ¿¡]/u', $normalized) === 1) {
            return 'es';
        }

        return Str::contains($normalized, [
            'hola',
            'buenas',
            'quiero',
            'reservar',
            'reserva',
            'cita',
            'servicio',
            'gracias',
            'español',
            'cancelar',
            'reprogramar',
        ]) ? 'es' : 'en';
    }

    private function language(Conversation $conversation): string
    {
        if (in_array($conversation->workspace->assistant_language, ['en', 'es'], true)) {
            return $conversation->workspace->assistant_language;
        }

        return $conversation->booking_data['language'] ?? 'en';
    }

    /** @param array<string, string> $replace */
    private function message(Conversation $conversation, string $key, array $replace = []): string
    {
        $message = __("bot.{$key}", $replace, $this->language($conversation));

        return str_replace('\\n', "\n", (string) $message);
    }

    private function formatDate(Conversation $conversation, CarbonInterface $date): string
    {
        return $date->locale($this->language($conversation))->translatedFormat('D, M j, Y');
    }

    /**
     * @return Collection<int, Service>
     */
    private function availableServices(Conversation $conversation): Collection
    {
        return $conversation->workspace->services()
            ->where('is_active', true)
            ->get();
    }

    /**
     * @param  array<int, CarbonImmutable>  $slots
     */
    private function numberedSlotList(array $slots, Conversation $conversation): string
    {
        return collect($slots)
            ->map(fn (CarbonImmutable $slot, int $index): string => ($index + 1).'. '.$this->formatDate($conversation, $slot).' '.$this->message($conversation, 'at').' '.$slot->format('g:i A'))
            ->implode("\n");
    }

    /**
     * @param  array<int, CarbonImmutable>  $slots
     * @return array<int, string>
     */
    private function slotValues(array $slots): array
    {
        return collect($slots)
            ->map(fn (CarbonImmutable $slot): string => $slot->format('Y-m-d H:i'))
            ->values()
            ->all();
    }

    private function resolveSlotReply(Conversation $conversation, string $body): string
    {
        $reply = trim($body);

        if (! ctype_digit($reply)) {
            return $reply;
        }

        $suggestedSlots = $conversation->booking_data['suggested_slots'] ?? [];

        return $suggestedSlots[((int) $reply) - 1] ?? $reply;
    }

    private function reply(Conversation $conversation, Message $incomingMessage, WhatsAppConnection $connection, string $body): void
    {
        try {
            $metaMessageId = $this->whatsApp->sendText($connection, $conversation->customer_wa_id, $body);
        } catch (Throwable $exception) {
            $incomingMessage->update(['status' => 'failed']);

            throw $exception;
        }

        $conversation->messages()->create([
            'meta_message_id' => $metaMessageId !== '' ? $metaMessageId : null,
            'direction' => 'outbound',
            'sender_type' => 'bot',
            'type' => 'text',
            'body' => $body,
            'status' => 'sent',
            'sent_at' => now(),
        ]);
        $incomingMessage->update(['status' => 'processed']);
    }
}
