<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryHold extends Model
{
    protected $fillable = [
        'screen_id',
        'booking_line_item_id',
        'booking_id',
        'held_by',
        'hold_start',
        'hold_end',
        'spot_duration',
        'spots_per_hour',
        'hold_type',
        'status',
        'expires_at',
        'released_at',
        'release_reason',
    ];

    protected $casts = [
        'hold_start'  => 'date',
        'hold_end'    => 'date',
        'expires_at'  => 'datetime',
        'released_at' => 'datetime',
    ];

    public static array $holdTypes = [
        'soft' => 'Soft Hold (tạm giữ)',
        'hard' => 'Hard Hold (đã xác nhận)',
    ];

    public static array $statuses = [
        'active'    => 'Đang giữ',
        'released'  => 'Đã giải phóng',
        'expired'   => 'Hết hạn',
        'converted' => 'Đã chuyển thành Hard Hold',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function screen(): BelongsTo
    {
        return $this->belongsTo(Screen::class);
    }

    public function bookingLineItem(): BelongsTo
    {
        return $this->belongsTo(BookingLineItem::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function heldBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'held_by');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeSoft($query)
    {
        return $query->where('hold_type', 'soft');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'active')
                     ->where('hold_type', 'soft')
                     ->where('expires_at', '<', now());
    }

    public function scopeForScreen($query, int $screenId, string $start, string $end)
    {
        return $query->where('screen_id', $screenId)
                     ->where('status', 'active')
                     ->where('hold_start', '<=', $end)
                     ->where('hold_end', '>=', $start);
    }
}
