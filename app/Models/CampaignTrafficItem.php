<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampaignTrafficItem extends Model
{
    protected $fillable = [
        'campaign_traffic_id',
        'booking_line_item_id',
        'screen_id',
        'creative_asset_id',
        'start_date',
        'end_date',
        'dayparting_override',
        'cms_placement_id',
        'spot_duration',
        'spots_per_hour',
        'status',
        'test_played_at',
        'test_played_by_name',
        'qa_note',
        'qa_screenshot_path',
    ];

    protected $casts = [
        'start_date'         => 'date',
        'end_date'           => 'date',
        'dayparting_override' => 'array',
        'test_played_at'     => 'datetime',
    ];

    public static array $statuses = [
        'draft'     => 'Nháp',
        'uploaded'  => 'Đã upload lên CMS',
        'scheduled' => 'Đã lên lịch',
        'qa_pending' => 'Chờ QA on-screen',
        'qa_passed' => 'QA Pass',
        'qa_failed' => 'QA Fail',
        'live'      => 'Đang phát',
        'completed' => 'Hoàn thành',
        'cancelled' => 'Đã huỷ',
    ];

    public static array $statusColors = [
        'draft'      => 'gray',
        'uploaded'   => 'info',
        'scheduled'  => 'info',
        'qa_pending' => 'warning',
        'qa_passed'  => 'success',
        'qa_failed'  => 'danger',
        'live'       => 'success',
        'completed'  => 'gray',
        'cancelled'  => 'danger',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function campaignTraffic(): BelongsTo
    {
        return $this->belongsTo(CampaignTraffic::class);
    }

    public function bookingLineItem(): BelongsTo
    {
        return $this->belongsTo(BookingLineItem::class);
    }

    public function screen(): BelongsTo
    {
        return $this->belongsTo(Screen::class);
    }

    public function creativeAsset(): BelongsTo
    {
        return $this->belongsTo(CreativeAsset::class);
    }

    public function qaChecklists(): HasMany
    {
        return $this->hasMany(TrafficQaChecklist::class);
    }
}
