<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BriefRevision extends Model
{
    protected $fillable = [
        'brief_id',
        'revision_number',
        'customer_file_path',
        'planning_file_path',
        'customer_note',
        'adops_note',
        'status',
        'sent_by',
        'sent_at',
        'is_final',
    ];

    protected $casts = [
        'sent_at'   => 'datetime',
        'is_final'  => 'boolean',
    ];

    public static array $statuses = [
        'draft'             => 'Nháp',
        'sent_to_customer'  => 'Đã gửi khách',
        'customer_feedback' => 'Khách phản hồi',
        'approved'          => 'Đã duyệt',
        'rejected'          => 'Từ chối',
        'superseded'        => 'Đã thay thế',
    ];

    public static array $statusColors = [
        'draft'             => 'gray',
        'sent_to_customer'  => 'info',
        'customer_feedback' => 'warning',
        'approved'          => 'success',
        'rejected'          => 'danger',
        'superseded'        => 'gray',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function brief(): BelongsTo
    {
        return $this->belongsTo(Brief::class);
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
