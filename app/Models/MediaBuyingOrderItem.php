<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\BookingLineItem;

class MediaBuyingOrderItem extends Model
{
    protected $fillable = [
        'media_buying_order_id',
        'booking_line_item_id',
        'ad_network_id',
        'screen_id',
        'description',
        'location_city',
        'start_date',
        'end_date',
        'screen_count',
        'days',
        'spot_duration',
        'pricing_model',
        'unit_price',
        'cpm',
        'total_price',
        'note',
    ];

    protected $casts = [
        'start_date'  => 'date',
        'end_date'    => 'date',
        'unit_price'  => 'decimal:2',
        'cpm'         => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    // ─── Auto-calculate total_price ───────────────────────────────────────────

    protected static function booted(): void
    {
        $recalc = function (MediaBuyingOrderItem $item) {
            $item->total_price = $item->unit_price * $item->screen_count * $item->days;
        };

        static::creating($recalc);
        static::updating($recalc);

        static::saved(function (MediaBuyingOrderItem $item) {
            $item->mediaBuyingOrder->recalculateTotal();
        });

        static::deleted(function (MediaBuyingOrderItem $item) {
            $item->mediaBuyingOrder->recalculateTotal();
        });
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function mediaBuyingOrder(): BelongsTo
    {
        return $this->belongsTo(MediaBuyingOrder::class);
    }

    public function adNetwork(): BelongsTo
    {
        return $this->belongsTo(AdNetwork::class);
    }

    public function screen(): BelongsTo
    {
        return $this->belongsTo(Screen::class);
    }

    public function bookingLineItem(): BelongsTo
    {
        return $this->belongsTo(BookingLineItem::class);
    }
}
