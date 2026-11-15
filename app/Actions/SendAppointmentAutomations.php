<?php

namespace App\Actions;

use App\Models\Appointment;
use App\Services\WhatsAppClient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SendAppointmentAutomations
{
    public function __construct(private WhatsAppClient $whatsApp) {}

    /** @return array{sent: int, failed: int} */
    public function handle(int $limit = 100): array
    {
        $result = ['sent' => 0, 'failed' => 0];

        $this->reminderCandidates($limit)->each(function (Appointment $appointment) use (&$result): void {
            $this->attempt($appointment, 'reminder', $result);
        });
        $this->followupCandidates($limit)->each(function (Appointment $appointment) use (&$result): void {
            $this->attempt($appointment, 'followup', $result);
        });

        return $result;
    }

    /** @return Collection<int, Appointment> */
    private function reminderCandidates(int $limit): Collection
    {
        return $this->baseQuery()
            ->whereHas('workspace', fn (Builder $query): Builder => $query->where('reminder_enabled', true))
            ->whereNull('reminder_sent_at')
            ->whereBetween('starts_at', [now(), now()->addHours(72)])
            ->limit($limit)
            ->get()
            ->filter(fn (Appointment $appointment): bool => $appointment->workspace->reminder_enabled
                && $appointment->starts_at->lessThanOrEqualTo(now()->addHours($appointment->workspace->reminder_hours_before)));
    }

    /** @return Collection<int, Appointment> */
    private function followupCandidates(int $limit): Collection
    {
        return $this->baseQuery()
            ->whereHas('workspace', fn (Builder $query): Builder => $query->where('followup_enabled', true))
            ->whereNull('followup_sent_at')
            ->whereBetween('ends_at', [now()->subDays(7), now()])
            ->limit($limit)
            ->get()
            ->filter(fn (Appointment $appointment): bool => $appointment->workspace->followup_enabled
                && $appointment->ends_at->lessThanOrEqualTo(now()->subHours($appointment->workspace->followup_hours_after)));
    }

    private function baseQuery(): Builder
    {
        return Appointment::query()
            ->with(['workspace.whatsAppConnection'])
            ->where('status', 'confirmed')
            ->orderBy('starts_at')
            ->orderBy('id');
    }

    /** @param array{sent: int, failed: int} $result */
    private function attempt(Appointment $appointment, string $type, array &$result): void
    {
        try {
            $sent = Cache::lock("appointment-automation:{$appointment->id}:{$type}", 60)->get(
                fn (): bool => $this->send($appointment->fresh(['workspace.whatsAppConnection']), $type),
            );

            if ($sent) {
                $result['sent']++;
            }
        } catch (Throwable $exception) {
            report($exception);
            $result['failed']++;
        }
    }

    private function send(Appointment $appointment, string $type): bool
    {
        $sentAtColumn = $type.'_sent_at';

        if ($appointment->{$sentAtColumn} !== null) {
            return false;
        }

        $workspace = $appointment->workspace;
        $connection = $workspace->whatsAppConnection;

        if ($connection?->status !== 'connected') {
            return false;
        }

        $language = $this->language($appointment);
        $defaultTemplate = (string) __("bot.automation_{$type}", [], $language);
        $template = $type === 'reminder'
            ? ($workspace->reminder_message ?: $defaultTemplate)
            : ($workspace->followup_message ?: $defaultTemplate);
        $body = strtr($template, $this->templateValues($appointment, $language));
        $metaMessageId = $this->whatsApp->sendText($connection, $appointment->customer_phone, $body);
        $conversation = $workspace->conversations()->firstOrCreate(
            ['customer_wa_id' => $appointment->customer_phone],
            [
                'whats_app_connection_id' => $connection->id,
                'customer_id' => $appointment->customer_id,
                'customer_name' => $appointment->customer_name,
                'status' => 'booked',
                'booking_stage' => 'completed',
                'booking_data' => ['appointment_id' => $appointment->id, 'service_id' => $appointment->service_id, 'language' => $language],
                'last_message_at' => now(),
            ],
        );
        $conversation->messages()->create([
            'meta_message_id' => $metaMessageId !== '' ? $metaMessageId : null,
            'direction' => 'outbound',
            'sender_type' => 'bot',
            'type' => 'text',
            'body' => $body,
            'status' => 'sent',
            'sent_at' => now(),
        ]);
        $conversation->update(['last_message_at' => now()]);
        $appointment->update([$sentAtColumn => now()]);

        return true;
    }

    /** @return array<string, string> */
    private function templateValues(Appointment $appointment, string $language): array
    {
        $localStart = $appointment->starts_at->setTimezone($appointment->timezone);

        return [
            '{name}' => $appointment->customer_name,
            '{service}' => $appointment->service ?: (string) __('bot.appointment_noun', [], $language),
            '{date}' => $localStart->locale($language)->translatedFormat('D, M j, Y'),
            '{time}' => $localStart->format('g:i A'),
        ];
    }

    private function language(Appointment $appointment): string
    {
        $configuredLanguage = $appointment->workspace->assistant_language;

        if (in_array($configuredLanguage, ['en', 'es'], true)) {
            return $configuredLanguage;
        }

        $conversation = $appointment->workspace->conversations()
            ->where('customer_wa_id', $appointment->customer_phone)
            ->first();

        return $conversation?->booking_data['language'] ?? 'en';
    }
}
