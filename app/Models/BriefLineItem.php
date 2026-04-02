<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BriefLineItem extends Model
{
    protected $fillable = [
        'brief_id',
        'platform',
        'placement',
        'format',
        'location',
        'targeting',
        'start_date',
        'end_date',
        'live_days',
        'unit',
        'guaranteed_units',
        'unit_cost',
        'daily_spots',
        'line_budget',
        'est_impression',
        'avg_multiplier',
        'est_impression_day',
        'est_ad_spot',
        'sort_order',
    ];

    protected $casts = [
        'start_date'  => 'date',
        'end_date'    => 'date',
        'unit_cost'   => 'decimal:2',
        'line_budget' => 'decimal:2',
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
