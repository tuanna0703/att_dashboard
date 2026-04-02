<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Screen extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'venue_name',
        'venue_type',
        'location_city',
        'location_address',
        'province',
        'latitude',
        'longitude',
        'width_px',
        'height_px',
        'resolution',
        'total_slots_per_hour',
        'operational_hours',
        'slot_duration_seconds',
        'rate_card_cpm',
        'rate_card_daily',
        'ad_network',
        'ad_network_id',
        'status',
        'notes',
    ];

    protected $casts = [
        'latitude'         => 'decimal:7',
        'longitude'        => 'decimal:7',
        'rate_card_cpm'    => 'decimal:2',
        'rate_card_daily'  => 'decimal:2',
    ];

    public static array $venueTypes = [
        'mall'        => 'Trung tâm thương mại',
        'airport'     => 'Sân bay',
        'hospital'    => 'Bệnh viện',
        'office'      => 'Tòa nhà văn phòng',
        'hotel'       => 'Khách sạn',
        'outdoor'     => 'Ngoài trời',
        'supermarket' => 'Siêu thị',
        'university'  => 'Trường đại học',
        'gym'         => 'Phòng tập gym',
        'other'       => 'Khác',
    ];

    public static array $statuses = [
        'active'      => 'Đang hoạt động',
        'inactive'    => 'Ngừng hoạt động',
        'maintenance' => 'Bảo trì',
    ];

    public static array $statusColors = [
        'active'      => 'success',
        'inactive'    => 'gray',
        'maintenance' => 'warning',
    ];

    // ─── Computed ─────────────────────────────────────────────────────────────

    /** Total spots available per day */
    public function getDailySlotsAttribute(): int
    {
        return $this->total_slots_per_hour * $this->operational_hours;
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function adNetwork(): BelongsTo
    {
        return $this->belongsTo(AdNetwork::class);
    }

    public function planLineItems(): HasMany
    {
        return $this->hasMany(PlanLineItem::class);
    }

    public function bookingLineItems(): HasMany
    {
        return $this->hasMany(BookingLineItem::class);
    }

    public function inventoryHolds(): HasMany
    {
        return $this->hasMany(InventoryHold::class);
    }

    public function availabilityLogs(): HasMany
    {
        return $this->hasMany(ScreenAvailabilityLog::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByCity($query, string $city)
    {
        return $query->where('location_city', $city);
    }

    public function scopeByVenueType($query, string $type)
    {
        return $query->where('venue_type', $type);
    }
}
