<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrafficQaChecklist extends Model
{
    protected $fillable = [
        'campaign_traffic_item_id',
        'checked_by',
        'check_type',
        'result',
        'is_blocking',
        'note',
        'checked_at',
    ];

    protected $casts = [
        'is_blocking' => 'boolean',
        'checked_at'  => 'datetime',
    ];

    public static array $checkTypes = [
        'video_plays'       => 'Video phát được',
        'correct_creative'  => 'Đúng creative',
        'correct_duration'  => 'Đúng thời lượng',
        'audio_ok'          => 'Âm thanh ổn',
        'display_fullscreen' => 'Hiển thị full screen',
        'schedule_correct'  => 'Đúng lịch phát',
        'loop_correct'      => 'Loop đúng',
        'no_glitch'         => 'Không bị giật/lỗi',
    ];

    public static array $results = [
        'pass'    => 'Đạt',
        'fail'    => 'Không đạt',
        'warning' => 'Cảnh báo',
    ];

    public static array $resultColors = [
        'pass'    => 'success',
        'fail'    => 'danger',
        'warning' => 'warning',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function campaignTrafficItem(): BelongsTo
    {
        return $this->belongsTo(CampaignTrafficItem::class);
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
