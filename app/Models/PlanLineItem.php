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
        'start_date',
        'end_date',
        'live_days',
        'unit',
        'guaranteed_units',
        'unit_cost',
        'daily_spots',
        'line_budget',
        'est_impression',
        'est_impression_day',
        'est_ad_spot',
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
        'targeting'       => 'array',
        'start_date'      => 'date',
        'end_date'        => 'date',
        'guaranteed_units'=> 'decimal:2',
        'unit_cost'       => 'decimal:2',
        'line_budget'     => 'decimal:2',
        'confirmed_at'    => 'datetime',
        'rejected_at'     => 'datetime',
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

    // ─── Auto-calculate line_budget ───────────────────────────────────────────

    protected static function booted(): void
    {
        $calc = function (PlanLineItem $item) {
            if ($item->guaranteed_units !== null && $item->unit_cost !== null) {
                $item->line_budget = (float) $item->guaranteed_units * (float) $item->unit_cost;
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
