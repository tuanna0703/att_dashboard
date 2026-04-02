<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'booking_no',
        'brief_id',
        'brief_revision_id',
        'plan_id',
        'contract_id',
        'customer_id',
        'sale_id',
        'adops_id',
        'campaign_name',
        'start_date',
        'end_date',
        'total_budget',
        'tax_pct',
        'tax_amount',
        'grand_total',
        'status',
        'note',
    ];

    protected $casts = [
        'start_date'   => 'date',
        'end_date'     => 'date',
        'total_budget' => 'decimal:2',
        'tax_pct'      => 'decimal:2',
        'tax_amount'   => 'decimal:2',
        'grand_total'  => 'decimal:2',
    ];

    public static array $statuses = [
        'pending_contract'   => 'Chờ hợp đồng',
        'contract_signed'    => 'Đã ký HĐ',
        'campaign_active'    => 'Đang chạy',
        'campaign_completed' => 'Đã chạy xong',
        'acceptance_done'    => 'Đã nghiệm thu',
        'closed'             => 'Hoàn thành',
        'cancelled'          => 'Đã huỷ',
    ];

    public static array $statusColors = [
        'pending_contract'   => 'warning',
        'contract_signed'    => 'info',
        'campaign_active'    => 'success',
        'campaign_completed' => 'primary',
        'acceptance_done'    => 'primary',
        'closed'             => 'gray',
        'cancelled'          => 'danger',
    ];

    // ─── Auto-generate booking_no ─────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (Booking $booking) {
            if (empty($booking->booking_no)) {
                $year  = now()->format('Y');
                $count = static::whereYear('created_at', $year)->count() + 1;
                $booking->booking_no = 'BKG-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function brief(): BelongsTo
    {
        return $this->belongsTo(Brief::class);
    }

    public function finalRevision(): BelongsTo
    {
        return $this->belongsTo(BriefRevision::class, 'brief_revision_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

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

    public function mediaBuyingOrders(): HasMany
    {
        return $this->hasMany(MediaBuyingOrder::class);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(BookingLineItem::class)->orderBy('sort_order');
    }

    public function creativeSubmissions(): HasMany
    {
        return $this->hasMany(CreativeSubmission::class);
    }

    public function campaignTraffics(): HasMany
    {
        return $this->hasMany(CampaignTraffic::class);
    }

    public function readyToAirGate(): HasOne
    {
        return $this->hasOne(ReadyToAirGate::class);
    }

    // ─── Computed ─────────────────────────────────────────────────────────────

    /** Recalculate totals from line items */
    public function recalculateBudget(): void
    {
        $netTotal = $this->lineItems()->sum('net_price');
        $taxAmt   = $netTotal * ($this->tax_pct / 100);

        $this->withoutEvents(function () use ($netTotal, $taxAmt) {
            $this->update([
                'total_budget' => $netTotal,
                'tax_amount'   => $taxAmt,
                'grand_total'  => $netTotal + $taxAmt,
            ]);
        });
    }

    /** Check if all line items are fully bought */
    public function checkFullyBought(): bool
    {
        return $this->lineItems()
            ->whereNotIn('buying_status', ['fully_bought', 'cancelled'])
            ->doesntExist();
    }
}
