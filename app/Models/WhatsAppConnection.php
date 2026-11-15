<?php

namespace App\Models;

use Database\Factories\WhatsAppConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['workspace_id', 'waba_id', 'phone_number_id', 'display_phone_number', 'verified_name', 'status', 'coexistence_enabled', 'access_token', 'connected_at'])]
#[Hidden(['access_token'])]
class WhatsAppConnection extends Model
{
    /** @use HasFactory<WhatsAppConnectionFactory> */
    use HasFactory;

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'coexistence_enabled' => 'boolean',
            'connected_at' => 'datetime',
        ];
    }
}
