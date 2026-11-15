<?php

namespace App\Models;

use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['owner_id', 'name', 'timezone', 'appointment_duration_minutes', 'buffer_minutes', 'reminder_enabled', 'reminder_hours_before', 'reminder_message', 'followup_enabled', 'followup_hours_after', 'followup_message', 'bot_instructions', 'bot_tone', 'assistant_language', 'bot_enabled'])]
class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory;

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function businessHours(): HasMany
    {
        return $this->hasMany(BusinessHour::class)->orderBy('day_of_week');
    }

    public function calendarConnection(): HasOne
    {
        return $this->hasOne(CalendarConnection::class);
    }

    public function whatsAppConnection(): HasOne
    {
        return $this->hasOne(WhatsAppConnection::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class)->orderBy('sort_order')->orderBy('id');
    }

    public function availabilityBlocks(): HasMany
    {
        return $this->hasMany(AvailabilityBlock::class)->orderBy('starts_at')->orderBy('id');
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class)->orderByDesc('last_contact_at')->orderByDesc('id');
    }

    protected function casts(): array
    {
        return [
            'bot_enabled' => 'boolean',
            'reminder_enabled' => 'boolean',
            'followup_enabled' => 'boolean',
        ];
    }
}
