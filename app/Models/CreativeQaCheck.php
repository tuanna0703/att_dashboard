<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreativeQaCheck extends Model
{
    protected $fillable = [
        'creative_asset_id',
        'checked_by',
        'check_type',
        'expected_value',
        'actual_value',
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
        'resolution'   => 'Độ phân giải',
        'duration'     => 'Thời lượng',
        'file_size'    => 'Dung lượng file',
        'format'       => 'Định dạng',
        'codec'        => 'Codec',
        'bitrate'      => 'Bitrate',
        'fps'          => 'Tốc độ khung hình',
        'content'      => 'Nội dung',
        'audio'        => 'Âm thanh',
        'aspect_ratio' => 'Tỉ lệ khung hình',
    ];

    public static array $results = [
        'pass'    => 'Đạt',
        'fail'    => 'Không đạt',
        'warning' => 'Cảnh báo',
        'skipped' => 'Bỏ qua',
    ];

    public static array $resultColors = [
        'pass'    => 'success',
        'fail'    => 'danger',
        'warning' => 'warning',
        'skipped' => 'gray',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function creativeAsset(): BelongsTo
    {
        return $this->belongsTo(CreativeAsset::class);
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
