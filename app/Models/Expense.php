<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'expense_no',
        'expense_date',
        'expense_category_id',
        'contract_id',
        'vendor_id',
        'total_amount',
        'payment_method',
        'company_bank_id',
        'reference_no',
        'recorded_by',
        'approved_by',
        'approved_at',
        'status',
        'rejection_reason',
        'note',
        'file_path',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'approved_at'  => 'datetime',
        'total_amount' => 'decimal:2',
    ];

    public static array $statuses = [
        'draft'    => 'Nháp',
        'pending'  => 'Chờ duyệt',
        'approved' => 'Đã duyệt',
        'rejected' => 'Từ chối',
        'paid'     => 'Đã thanh toán',
    ];

    public static array $statusColors = [
        'draft'    => 'gray',
        'pending'  => 'warning',
        'approved' => 'success',
        'rejected' => 'danger',
        'paid'     => 'primary',
    ];

    // ─── Auto-generate expense_no ─────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (Expense $expense) {
            if (empty($expense->expense_no)) {
                $year  = now()->format('Y');
                $count = static::whereYear('created_at', $year)->count() + 1;
                $expense->expense_no = 'PC-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function companyBank(): BelongsTo
    {
        return $this->belongsTo(CompanyBank::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ExpenseItem::class);
    }
}
