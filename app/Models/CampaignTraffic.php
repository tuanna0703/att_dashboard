<?php

namespace App\Models;

use App\Traits\GeneratesCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CampaignTraffic extends Model
{
    use SoftDeletes, GeneratesCode;

    const CODE_PREFIX = 'TRF';
    const CODE_FIELD  = 'traffic_no';

    protected $fillable = [
        'traffic_no',
        'booking_id',
        'creative_submission_id',
        'created_by',
        'cms_campaign_id',
        'cms_campaign_name',
        'flight_start',
        'flight_end',
        'dayparting',
        'frequency_cap_per_hour',
        'frequency_cap_per_day',
        'targeting_rules',
        'priority',
        'status',
        'go_live_at',
        'paused_at',
        'setup_notes',
    ];

    protected $casts = [
        'flight_start'    => 'date',
        'flight_end'      => 'date',
        'dayparting'      => 'array',
        'targeting_rules' => 'array',
        'go_live_at'      => 'datetime',
        'paused_at'       => 'datetime',
    ];

    public static array $statuses = [
        'draft'     => 'Nháp',
        'qa_pending' => 'Chờ QA',
        'qa_failed' => 'QA thất bại',
        'approved'  => 'Đã duyệt',
        'live'      => 'Đang phát',
        'paused'    => 'Tạm dừng',
        'completed' => 'Hoàn thành',
        'cancelled' => 'Đã huỷ',
    ];

    public static array $statusColors = [
        'draft'      => 'gray',
        'qa_pending' => 'warning',
        'qa_failed'  => 'danger',
        'approved'   => 'info',
        'live'       => 'success',
        'paused'     => 'warning',
        'completed'  => 'gray',
        'cancelled'  => 'danger',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function creativeSubmission(): BelongsTo
    {
        return $this->belongsTo(CreativeSubmission::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CampaignTrafficItem::class);
    }

    // ─── Computed ─────────────────────────────────────────────────────────────

    /** Check if all items have passed QA */
    public function allItemsQaPassed(): bool
    {
        return $this->items()->whereNotIn('status', ['qa_passed', 'cancelled'])->doesntExist();
    }
}
