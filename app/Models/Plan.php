<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'plan_no',
        'brief_id',
        'version',
        'budget',
        'screen_count',
        'note',
        'file_path',
        'sale_comment',
        'status',
        'adops_id',
        'responded_by',
        'responded_at',
    ];

    protected $casts = [
        'budget'       => 'decimal:2',
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

    public function adops(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adops_id');
    }

    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    public function booking(): HasOne
    {
        return $this->hasOne(Booking::class);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(PlanLineItem::class)->orderBy('sort_order');
    }

    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject')->orderByDesc('created_at');
    }

    // ─── Computed totals ──────────────────────────────────────────────────────

    public function recalculateTotals(): void
    {
        $totals = $this->lineItems()
            ->whereNot('status', 'rejected')
            ->selectRaw('SUM(line_budget) as total_budget, COUNT(*) as total_count')
            ->first();

        $this->withoutEvents(function () use ($totals) {
            $this->update([
                'budget'       => $totals->total_budget ?? 0,
                'screen_count' => $totals->total_count ?? 0,
            ]);
        });
    }
}
