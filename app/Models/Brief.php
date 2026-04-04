<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brief extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'brief_no',
        'customer_id',
        'sale_id',
        'adops_id',
        'campaign_name',
        'budget',
        'currency',
        'status',
        'current_revision_id',
        'note',
        'file_path',
    ];

    protected $casts = [
        'budget' => 'decimal:2',
    ];

    public static array $statuses = [
        'draft'             => 'Nháp',
        'sent_to_adops'     => 'Đã gửi AdOps',
        'planning_ready'    => 'Có planning',
        'sent_to_customer'  => 'Đã gửi khách',
        'customer_feedback' => 'Khách phản hồi',
        'confirmed'         => 'Khách confirm',
        'rejected'          => 'Từ chối',
        'converted'         => 'Đã tạo Booking',
    ];

    public static array $statusColors = [
        'draft'             => 'gray',
        'sent_to_adops'     => 'info',
        'planning_ready'    => 'warning',
        'sent_to_customer'  => 'info',
        'customer_feedback' => 'warning',
        'confirmed'         => 'success',
        'rejected'          => 'danger',
        'converted'         => 'primary',
    ];

    // ─── Auto-generate brief_no ───────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (Brief $brief) {
            if (empty($brief->brief_no)) {
                $year  = now()->format('Y');
                $count = static::whereYear('created_at', $year)->count() + 1;
                $brief->brief_no = 'BRF-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sale_id');
    }

    public function adops(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adops_id');
    }

    public function currentRevision(): BelongsTo
    {
        return $this->belongsTo(BriefRevision::class, 'current_revision_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(BriefRevision::class)->orderBy('revision_number');
    }

    public function adNetworks(): BelongsToMany
    {
        return $this->belongsToMany(AdNetwork::class, 'brief_ad_networks')
            ->withPivot(['screen_count', 'note'])
            ->withTimestamps();
    }

    public function briefAdNetworks(): HasMany
    {
        return $this->hasMany(BriefAdNetwork::class);
    }

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class)->orderBy('version');
    }

    public function acceptedPlan(): HasOne
    {
        return $this->hasOne(Plan::class)->where('status', 'accepted')->latestOfMany();
    }

    public function briefLineItems(): HasMany
    {
        return $this->hasMany(BriefLineItem::class)->orderBy('sort_order');
    }

    public function recalcBudget(): void
    {
        $total = $this->briefLineItems()->sum('line_budget');
        $this->updateQuietly(['budget' => $total ?: null]);
    }

    public function booking(): HasOne
    {
        return $this->hasOne(Booking::class);
    }

    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject')->orderByDesc('created_at');
    }
}
