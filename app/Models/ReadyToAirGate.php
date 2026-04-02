<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadyToAirGate extends Model
{
    protected $fillable = [
        'booking_id',
        'booking_fully_bought',
        'contract_signed',
        'creative_approved',
        'campaign_trafficked',
        'qa_passed',
        'payment_received',
        'content_compliance_ok',
        'all_conditions_met',
        'gate_passed_at',
        'approved_by',
    ];

    protected $casts = [
        'booking_fully_bought'   => 'boolean',
        'contract_signed'        => 'boolean',
        'creative_approved'      => 'boolean',
        'campaign_trafficked'    => 'boolean',
        'qa_passed'              => 'boolean',
        'payment_received'       => 'boolean',
        'content_compliance_ok'  => 'boolean',
        'all_conditions_met'     => 'boolean',
        'gate_passed_at'         => 'datetime',
    ];

    /** All 7 gate conditions that must be true */
    public const CONDITIONS = [
        'booking_fully_bought',
        'contract_signed',
        'creative_approved',
        'campaign_trafficked',
        'qa_passed',
        'payment_received',
        'content_compliance_ok',
    ];

    public static array $conditionLabels = [
        'booking_fully_bought'  => 'Inventory đã mua đủ',
        'contract_signed'       => 'Hợp đồng đã ký',
        'creative_approved'     => 'Creative đã QA Pass',
        'campaign_trafficked'   => 'Đã setup trên CMS',
        'qa_passed'             => 'Test on-screen pass',
        'payment_received'      => 'Đã thu tiền',
        'content_compliance_ok' => 'Nội dung đúng quy định',
    ];

    // ─── Computed ─────────────────────────────────────────────────────────────

    public function evaluateAll(): bool
    {
        $allMet = collect(self::CONDITIONS)->every(fn ($c) => (bool) $this->{$c});

        if ($allMet && ! $this->all_conditions_met) {
            $this->update([
                'all_conditions_met' => true,
                'gate_passed_at'     => now(),
            ]);
        }

        return $allMet;
    }

    public function updateCondition(string $condition, bool $value): void
    {
        if (! in_array($condition, self::CONDITIONS)) {
            return;
        }
        $this->update([$condition => $value]);
        $this->evaluateAll();
    }

    public function getProgressAttribute(): array
    {
        $total = count(self::CONDITIONS);
        $met   = collect(self::CONDITIONS)->filter(fn ($c) => (bool) $this->{$c})->count();

        return [
            'met'        => $met,
            'total'      => $total,
            'percentage' => round(($met / $total) * 100),
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
