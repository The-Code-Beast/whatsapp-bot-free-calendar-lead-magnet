<?php

namespace App\Models;

use Database\Factories\ServiceBusinessHourFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['service_id', 'day_of_week', 'opens_at', 'closes_at', 'is_enabled'])]
class ServiceBusinessHour extends Model
{
    /** @use HasFactory<ServiceBusinessHourFactory> */
    use HasFactory;

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }
}
