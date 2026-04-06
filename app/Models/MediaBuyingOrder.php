<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MediaBuyingOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_no',
        'contract_id',
        'booking_id',
        'created_by',
        'dept_head_id',
        'dept_head_approved_at',
        'finance_approved_by',
        'finance_approved_at',
        'buyer_id',
        'buyer_executed_at',
        'total_amount',
        'status',
        'rejection_reason',
        'note',
        'file_path',
    ];

    protected $casts = [
        'dept_head_approved_at' => 'datetime',
        'finance_approved_at'   => 'datetime',
        'buyer_executed_at'     => 'datetime',
        'total_amount'          => 'decimal:2',
    ];

    public static array $statuses = [
        'draft'            => 'Nháp',
        'pending_dept'     => 'Chờ trưởng phòng',
        'dept_approved'    => 'TP đã duyệt',
        'pending_finance'  => 'Chờ kết toán',
        'finance_approved' => 'Kết toán đã duyệt',
        'sent_to_buyer'    => 'Đã gửi Buyer',
        'executed'         => 'Buyer đã thực hiện',
        'completed'        => 'Hoàn thành',
        'rejected'         => 'Từ chối',
    ];

    public static array $statusColors = [
        'draft'            => 'gray',
        'pending_dept'     => 'warning',
        'dept_approved'    => 'info',
        'pending_finance'  => 'warning',
        'finance_approved' => 'info',
        'sent_to_buyer'    => 'primary',
        'executed'         => 'success',
        'completed'        => 'success',
        'rejected'         => 'danger',
    ];

    // ─── Auto-generate order_no ───────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (MediaBuyingOrder $order) {
            if (empty($order->order_no)) {
                $year   = now()->format('Y');
                $prefix = 'MBO-' . $year . '-';
                $last   = static::withTrashed()
                    ->where('order_no', 'like', $prefix . '%')
                    ->orderByRaw('CAST(SUBSTRING(order_no, ' . (strlen($prefix) + 1) . ') AS UNSIGNED) DESC')
                    ->value('order_no');
                $next   = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;
                $order->order_no = $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deptHead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dept_head_id');
    }

    public function financeApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finance_approved_by');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MediaBuyingOrderItem::class);
    }

    // ─── Computed ─────────────────────────────────────────────────────────────

    public function recalculateTotal(): void
    {
        $this->total_amount = $this->items()->sum('total_price');
        $this->saveQuietly();
    }
}
