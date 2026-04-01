<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'plan_no',
        'brief_id',
        'version',
        'campaign_name',
        'start_date',
        'end_date',
        'budget',
        'cpm',
        'screen_count',
        'duration_days',
        'note',
        'file_path',
        'sale_comment',
        'status',
        'created_by',
        'responded_by',
        'responded_at',
    ];

    protected $casts = [
        'start_date'   => 'date',
        'end_date'     => 'date',
        'budget'       => 'decimal:2',
        'cpm'          => 'decimal:2',
        'responded_at' => 'datetime',
    ];

    public static array $statuses = [
        'draft'     => 'Nháp',
        'submitted' => 'Chờ duyệt',
        'accepted'  => 'Được chấp nhận',
        're_plan'   => 'Cần điều chỉnh',
        'rejected'  => 'Từ chối',
    ];

    public static array $statusColors = [
        'draft'     => 'gray',
        'submitted' => 'info',
        'accepted'  => 'success',
        're_plan'   => 'warning',
        'rejected'  => 'danger',
    ];

    // ─── Auto-generate plan_no ────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (Plan $plan) {
            if (empty($plan->plan_no)) {
                $year  = now()->format('Y');
                $count = static::whereYear('created_at', $year)->count() + 1;
                $plan->plan_no = 'PLN-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
            }

            // Auto version per brief
            if (empty($plan->version)) {
                $plan->version = Plan::where('brief_id', $plan->brief_id)->max('version') + 1;
            }
        });
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function brief(): BelongsTo
    {
        return $this->belongsTo(Brief::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    public function booking(): HasOne
    {
        return $this->hasOne(Booking::class);
    }
}
