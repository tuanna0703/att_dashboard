<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScreenAvailabilityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'screen_id',
        'date',
        'total_slots_per_hour',
        'operational_hours',
        'total_daily_slots',
        'sold_slots',
        'held_slots',
        'available_slots',
        'fill_rate_pct',
        'calculated_at',
    ];

    protected $casts = [
        'date'              => 'date',
        'total_daily_slots' => 'decimal:0',
        'sold_slots'        => 'decimal:0',
        'held_slots'        => 'decimal:0',
        'available_slots'   => 'decimal:0',
        'fill_rate_pct'     => 'decimal:2',
        'calculated_at'     => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function screen(): BelongsTo
    {
        return $this->belongsTo(Screen::class);
    }
}
