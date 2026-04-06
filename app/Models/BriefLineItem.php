<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BriefLineItem extends Model
{
    protected $fillable = [
        'brief_id',
        'format',
        'targeting',
        // Location
        'city',
        'qty_location',
        'qty_screen',
        // Dates
        'start_date',
        'end_date',
        'live_days',
        // Airing
        'time_from',
        'time_to',
        'total_hours',
        'sov',
        'duration_seconds',
        'frequency_minutes',
        'daily_spots',
        // Buying weeks
        'buy_weeks',
        'foc_weeks',
        'total_weeks',
        // Pricing
        'unit',
        'guaranteed_units',
        'unit_cost',
        'line_budget',
        'gross_amount',
        'vat_rate',
        // KPI
        'est_impression',
        'est_impression_day',
        'est_ad_spot',
        'kpi_multiplier',
        // Meta
        'sort_order',
    ];

    protected $casts = [
        'targeting'         => 'array',
        'start_date'        => 'date',
        'end_date'          => 'date',
        'unit_cost'         => 'decimal:2',
        'line_budget'       => 'decimal:2',
        'gross_amount'      => 'decimal:2',
        'vat_rate'          => 'decimal:2',
        'sov'               => 'decimal:2',
        'total_hours'       => 'decimal:1',
        'frequency_minutes' => 'decimal:1',
    ];

    public static array $units = [
        'cpm' => 'CPM',
        'cpd' => 'CPD',
        'io'  => 'I/O (Spots/Day)',
    ];

    protected static function booted(): void
    {
        static::saved(fn (self $item) => $item->brief?->recalcBudget());
        static::deleted(fn (self $item) => $item->brief?->recalcBudget());
    }

    public function brief(): BelongsTo
    {
        return $this->belongsTo(Brief::class);
    }
}
