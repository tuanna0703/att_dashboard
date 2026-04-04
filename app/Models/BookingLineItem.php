<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingLineItem extends Model
{
    protected $fillable = [
        'booking_id',
        'plan_line_item_id',
        'format',
        'targeting',
        'targeting_names',
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
        'buying_status',
        'notes',
        'sort_order',
    ];

    protected $casts = [
        'targeting'       => 'array',
        'targeting_names' => 'array',
        'start_date'      => 'date',
        'end_date'        => 'date',
        'guaranteed_units'=> 'decimal:2',
        'unit_cost'       => 'decimal:2',
        'line_budget'     => 'decimal:2',
    ];

    public static array $buyingStatuses = [
        'pending'      => 'Chờ mua',
        'partial'      => 'Mua một phần',
        'fully_bought' => 'Đã mua đủ',
        'cancelled'    => 'Đã huỷ',
    ];

    public static array $buyingStatusColors = [
        'pending'      => 'gray',
        'partial'      => 'warning',
        'fully_bought' => 'success',
        'cancelled'    => 'danger',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function planLineItem(): BelongsTo
    {
        return $this->belongsTo(PlanLineItem::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('buying_status', 'pending');
    }

    public function scopeFullyBought($query)
    {
        return $query->where('buying_status', 'fully_bought');
    }
}
