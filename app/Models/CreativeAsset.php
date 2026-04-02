<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class CreativeAsset extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'creative_submission_id',
        'booking_line_item_id',
        'original_filename',
        'stored_filename',
        'storage_path',
        'storage_disk',
        'mime_type',
        'file_size_bytes',
        'checksum_md5',
        'asset_type',
        'duration_seconds',
        'width',
        'height',
        'resolution',
        'aspect_ratio',
        'codec',
        'bitrate_kbps',
        'fps',
        'qa_status',
        'qa_reviewed_by',
        'qa_reviewed_at',
        'qa_notes',
        'uploaded_to_cms',
        'cms_asset_id',
        'cms_uploaded_at',
    ];

    protected $casts = [
        'aspect_ratio'    => 'decimal:2',
        'fps'             => 'decimal:2',
        'qa_reviewed_at'  => 'datetime',
        'cms_uploaded_at' => 'datetime',
        'uploaded_to_cms' => 'boolean',
    ];

    public static array $assetTypes = [
        'video'  => 'Video',
        'image'  => 'Hình ảnh',
        'html5'  => 'HTML5',
        'audio'  => 'Audio',
    ];

    public static array $qaStatuses = [
        'pending' => 'Chờ QA',
        'passed'  => 'Đạt',
        'failed'  => 'Không đạt',
        'warning' => 'Cảnh báo',
    ];

    public static array $qaStatusColors = [
        'pending' => 'gray',
        'passed'  => 'success',
        'failed'  => 'danger',
        'warning' => 'warning',
    ];

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->storage_disk)->url($this->storage_path);
    }

    public function getFileSizeFormattedAttribute(): string
    {
        $bytes = $this->file_size_bytes;
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        return round($bytes / 1024, 2) . ' KB';
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function creativeSubmission(): BelongsTo
    {
        return $this->belongsTo(CreativeSubmission::class);
    }

    public function bookingLineItem(): BelongsTo
    {
        return $this->belongsTo(BookingLineItem::class);
    }

    public function qaReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'qa_reviewed_by');
    }

    public function qaChecks(): HasMany
    {
        return $this->hasMany(CreativeQaCheck::class);
    }

    public function campaignTrafficItems(): HasMany
    {
        return $this->hasMany(CampaignTrafficItem::class);
    }
}
