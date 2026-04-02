<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanLineItem extends Model
{
    protected $fillable = [
        'plan_id',
        'screen_id',
        'venue_name',
        'venue_type',
        'location_city',
        'screen_code',
        'start_date',
        'end_date',
        'spot_duration',
        'spots_per_hour',
        'daily_hours',
        'total_spots',
        'pricing_model',
        'rate_card_price',
        'discount_pct',
        'net_price',
        'cpm',
        'estimated_impressions',
        'notes',
        'sort_order',
    ];

    protected $casts = [
        'start_date'           => 'date',
        'end_date'             => 'date',
        'rate_card_price'      => 'decimal:2',
        'discount_pct'         => 'decimal:2',
        'net_price'            => 'decimal:2',
        'cpm'                  => 'decimal:2',
        'total_spots'          => 'decimal:0',
        'estimated_impressions' => 'decimal:0',
    ];

    // ─── Auto-calculate derived fields ───────────────────────────────────────

    protected static function booted(): void
    {
        $calc = function (PlanLineItem $item) {
            // Total spots = spots_per_hour × daily_hours × duration_days
            if ($item->spots_per_hour && $item->daily_hours && $item->start_date && $item->end_date) {
                $days = $item->start_date->diffInDays($item->end_date) + 1;
                $item->total_spots = $item->spots_per_hour * $item->daily_hours * $days;
            }

            // Net price after discount
            if ($item->rate_card_price !== null && $item->discount_pct !== null) {
                $item->net_price = $item->rate_card_price * (1 - $item->discount_pct / 100);
            }

            // Auto-populate screen info from screen FK if provided
            if ($item->screen_id && ! $item->isDirty('venue_name')) {
                $screen = Screen::find($item->screen_id);
                if ($screen) {
                    $item->venue_name   = $item->venue_name    ?: $screen->venue_name;
                    $item->venue_type   = $item->venue_type    ?: $screen->venue_type;
                    $item->location_city = $item->location_city ?: $screen->location_city;
                    $item->screen_code  = $item->screen_code   ?: $screen->code;
                }
            }
        };

        static::creating($calc);
        static::updating($calc);

        // Recalculate plan totals when line item changes
        static::saved(fn (PlanLineItem $item) => $item->plan->recalculateTotals());
        static::deleted(fn (PlanLineItem $item) => $item->plan->recalculateTotals());
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function screen(): BelongsTo
    {
        return $this->belongsTo(Screen::class);
    }

    public function bookingLineItems(): HasMany
    {
        return $this->hasMany(BookingLineItem::class);
    }
}
