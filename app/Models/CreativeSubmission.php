<?php

namespace App\Models;

use App\Traits\GeneratesCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CreativeSubmission extends Model
{
    use SoftDeletes, GeneratesCode;

    const CODE_PREFIX = 'CR';
    const CODE_FIELD  = 'submission_no';

    protected $fillable = [
        'submission_no',
        'booking_id',
        'customer_id',
        'submitted_by',
        'assigned_qa_to',
        'campaign_name',
        'version',
        'required_specs',
        'status',
        'submission_note',
        'qa_summary',
        'submitted_at',
        'qa_completed_at',
    ];

    protected $casts = [
        'required_specs'   => 'array',
        'submitted_at'     => 'datetime',
        'qa_completed_at'  => 'datetime',
    ];

    public static array $statuses = [
        'draft'            => 'Nháp',
        'submitted'        => 'Đã nộp',
        'qa_in_progress'   => 'Đang QA',
        'approved'         => 'Đã duyệt',
        'rejected'         => 'Từ chối',
        'revision_pending' => 'Chờ chỉnh sửa',
        'superseded'       => 'Đã thay thế',
    ];

    public static array $statusColors = [
        'draft'            => 'gray',
        'submitted'        => 'info',
        'qa_in_progress'   => 'warning',
        'approved'         => 'success',
        'rejected'         => 'danger',
        'revision_pending' => 'warning',
        'superseded'       => 'gray',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function assignedQaTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_qa_to');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(CreativeAsset::class);
    }

    public function campaignTraffics(): HasMany
    {
        return $this->hasMany(CampaignTraffic::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePendingQa($query)
    {
        return $query->whereIn('status', ['submitted', 'qa_in_progress']);
    }
}
