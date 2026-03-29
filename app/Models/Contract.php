<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'contract_code',
        'name',
        'customer_id',
        'contract_type',
        'signed_date',
        'start_date',
        'end_date',
        'total_value_estimated',
        'currency',
        'customer_contact_id',
        'sale_owner_id',
        'account_owner_id',
        'finance_owner_id',
        'status',
        'note',
        'file_path',
    ];

    protected $casts = [
        'signed_date'  => 'date',
        'start_date'   => 'date',
        'end_date'     => 'date',
        'total_value_estimated' => 'decimal:2',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerContact(): BelongsTo
    {
        return $this->belongsTo(CustomerContact::class);
    }

    public function saleOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sale_owner_id');
    }

    public function accountOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_owner_id');
    }

    public function financeOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finance_owner_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ContractLine::class);
    }

    public function acceptances(): HasMany
    {
        return $this->hasMany(Acceptance::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function paymentSchedules(): HasMany
    {
        return $this->hasMany(PaymentSchedule::class);
    }

    // ─── Computed ─────────────────────────────────────────────────────────────

    public function totalPaid(): float
    {
        return (float) $this->paymentSchedules()->where('status', 'paid')->sum('amount');
    }

    public function totalOutstanding(): float
    {
        return (float) $this->paymentSchedules()
            ->whereIn('status', ['pending', 'invoiced', 'partially_paid', 'overdue'])
            ->sum('amount');
    }

    public function totalOverdue(): float
    {
        return (float) $this->paymentSchedules()->where('status', 'overdue')->sum('amount');
    }
}
