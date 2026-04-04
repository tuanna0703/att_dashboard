<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'log_name',
        'event',
        'subject_type',
        'subject_id',
        'causer_id',
        'causer_name',
        'description',
        'properties',
        'created_at',
    ];

    protected $casts = [
        'properties' => 'array',
        'created_at' => 'datetime',
    ];

    // Human-readable labels for each event
    public static array $eventLabels = [
        'brief.created'        => 'Brief được tạo',
        'brief.sent_to_adops'  => 'Gửi AdOps',
        'plan.created'         => 'Plan được tạo',
        'plan.submitted'       => 'Gửi duyệt',
        'plan.accepted'        => 'Chấp nhận',
        'plan.re_plan'         => 'Yêu cầu điều chỉnh',
        'plan.rejected'        => 'Từ chối',
    ];

    public static array $eventColors = [
        'brief.created'        => 'gray',
        'brief.sent_to_adops'  => 'info',
        'plan.created'         => 'info',
        'plan.submitted'       => 'warning',
        'plan.accepted'        => 'success',
        'plan.re_plan'         => 'warning',
        'plan.rejected'        => 'danger',
    ];

    public static array $eventIcons = [
        'brief.created'        => 'heroicon-o-document-plus',
        'brief.sent_to_adops'  => 'heroicon-o-paper-airplane',
        'plan.created'         => 'heroicon-o-clipboard-document-list',
        'plan.submitted'       => 'heroicon-o-paper-airplane',
        'plan.accepted'        => 'heroicon-o-check-badge',
        'plan.re_plan'         => 'heroicon-o-arrow-path',
        'plan.rejected'        => 'heroicon-o-x-circle',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function causer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'causer_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForBrief(Builder $query, int $briefId): Builder
    {
        return $query->where(function (Builder $q) use ($briefId) {
            // Logs trực tiếp trên Brief
            $q->where('subject_type', Brief::class)
              ->where('subject_id', $briefId);
        })->orWhere(function (Builder $q) use ($briefId) {
            // Logs trên Plans thuộc Brief này
            $q->where('subject_type', Plan::class)
              ->whereIn('subject_id', Plan::where('brief_id', $briefId)->pluck('id'));
        });
    }

    public function scopeForPlan(Builder $query, int $planId): Builder
    {
        return $query->where('subject_type', Plan::class)
                     ->where('subject_id', $planId);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function getEventLabelAttribute(): string
    {
        return static::$eventLabels[$this->event] ?? $this->event;
    }

    public function getEventColorAttribute(): string
    {
        return static::$eventColors[$this->event] ?? 'gray';
    }

    public function getEventIconAttribute(): string
    {
        return static::$eventIcons[$this->event] ?? 'heroicon-o-information-circle';
    }
}
