<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingLineItem extends Model
{
    protected $fillable = [
        'booking_id',
        'plan_line_item_id',
        'screen_id',
        'venue_name',
        'venue_type',
        'location_city',
        'screen_code',
        'screen_snapshot',
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
        'booked_impressions',
        'buying_status',
        'bought_impressions',
        'sort_order',
        'notes',
    ];

    protected $casts = [
        'screen_snapshot'    => 'array',
        'start_date'         => 'date',
        'end_date'           => 'date',
        'rate_card_price'    => 'decimal:2',
        'discount_pct'       => 'decimal:2',
        'net_price'          => 'decimal:2',
        'cpm'                => 'decimal:2',
        'total_spots'        => 'decimal:0',
        'booked_impressions' => 'decimal:0',
        'bought_impressions' => 'decimal:0',
    ];

    public static array $buyingStatuses = [
        'pending'     => 'Chờ mua',
        'partial'     => 'Mua một phần',
        'fully_bought' => 'Đã mua đủ',
        'cancelled'   => 'Đã huỷ',
    ];

    public static array $buyingStatusColors = [
        'pending'      => 'gray',
        'partial'      => 'warning',
        'fully_bought' => 'success',
        'cancelled'    => 'danger',
    ];

    // ─── Auto-snapshot screen data on create ─────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (BookingLineItem $item) {
            if ($item->screen_id && empty($item->screen_snapshot)) {
                $screen = Screen::find($item->screen_id);
                if ($screen) {
                    $item->screen_snapshot = $screen->only([
                        'code', 'name', 'venue_name', 'venue_type',
                        'location_city', 'location_address', 'resolution',
                        'total_slots_per_hour', 'operational_hours',
                    ]);
                    $item->venue_name    = $item->venue_name    ?: $screen->venue_name;
                    $item->venue_type    = $item->venue_type    ?: $screen->venue_type;
                    $item->location_city = $item->location_city ?: $screen->location_city;
                    $item->screen_code   = $item->screen_code   ?: $screen->code;
                }
            }
        });
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function planLineItem(): BelongsTo
    {
        return $this->belongsTo(PlanLineItem::class);
    }

    public function screen(): BelongsTo
    {
        return $this->belongsTo(Screen::class);
    }

    public function inventoryHolds(): HasMany
    {
        return $this->hasMany(InventoryHold::class);
    }

    public function campaignTrafficItems(): HasMany
    {
        return $this->hasMany(CampaignTrafficItem::class);
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
