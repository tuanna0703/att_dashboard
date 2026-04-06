<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanLineItem extends Model
{
    protected $fillable = [
        'plan_id',
        'brief_line_item_id',
        'created_by',
        'source',
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
        // Status
        'status',
        'confirmed_by',
        'confirmed_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'notes',
        'sort_order',
    ];

    protected $casts = [
        'targeting'         => 'array',
        'start_date'        => 'date',
        'end_date'          => 'date',
        'guaranteed_units'  => 'decimal:2',
        'unit_cost'         => 'decimal:2',
        'line_budget'       => 'decimal:2',
        'gross_amount'      => 'decimal:2',
        'vat_rate'          => 'decimal:2',
        'sov'               => 'decimal:2',
        'total_hours'       => 'decimal:1',
        'frequency_minutes' => 'decimal:1',
        'confirmed_at'      => 'datetime',
        'rejected_at'       => 'datetime',
    ];

    public static array $statuses = [
        'pending'   => 'Chờ xác nhận',
        'confirmed' => 'Đã xác nhận',
        'rejected'  => 'Từ chối',
    ];

    public static array $statusColors = [
        'pending'   => 'warning',
        'confirmed' => 'success',
        'rejected'  => 'danger',
    ];

    public static array $sourceLabels = [
        'sale'  => 'Sale',
        'adops' => 'AdOps',
    ];

    public static array $sourceColors = [
        'sale'  => 'info',
        'adops' => 'primary',
    ];

    // ─── Auto-calculate ──────────────────────────────────────────────────────

    protected static function booted(): void
    {
        $calc = function (PlanLineItem $item) {
            // Total weeks
            if ($item->buy_weeks !== null) {
                $item->total_weeks = (int) $item->buy_weeks + (int) ($item->foc_weeks ?? 0);
            }

            // NET line_budget = qty_location × buy_weeks × unit_cost
            if ($item->unit_cost !== null && $item->buy_weeks !== null) {
                $qty = max(1, (int) ($item->qty_location ?? 1));
                $item->line_budget = $qty * (int) $item->buy_weeks * (float) $item->unit_cost;
            } elseif ($item->guaranteed_units !== null && $item->unit_cost !== null) {
                $item->line_budget = (float) $item->guaranteed_units * (float) $item->unit_cost;
            }

            // GROSS = NET × (1 + VAT%)
            $vatRate = (float) ($item->vat_rate ?? 8);
            $item->gross_amount = (float) $item->line_budget * (1 + $vatRate / 100);

            // KPI: est_ad_spot = daily_spots × qty_screen × total_weeks × 7
            if ($item->daily_spots && $item->qty_screen && $item->total_weeks) {
                $item->est_ad_spot = (int) $item->daily_spots * (int) $item->qty_screen * (int) $item->total_weeks * 7;
            }

            // est_impression = est_ad_spot × kpi_multiplier
            if ($item->est_ad_spot && $item->kpi_multiplier) {
                $item->est_impression = (int) $item->est_ad_spot * (int) $item->kpi_multiplier;
            }
        };

        static::creating($calc);
        static::updating($calc);

        static::saved(fn (PlanLineItem $item) => $item->plan->recalculateTotals());
        static::deleted(fn (PlanLineItem $item) => $item->plan->recalculateTotals());
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function briefLineItem(): BelongsTo
    {
        return $this->belongsTo(BriefLineItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function bookingLineItems(): HasMany
    {
        return $this->hasMany(BookingLineItem::class);
    }
}
