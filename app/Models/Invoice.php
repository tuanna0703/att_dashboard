<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'contract_id',
        'company_bank_id',
        'invoice_no',
        'invoice_date',
        'invoice_value',
        'vat_value',
        'status',
        'file_path',
        'note',
    ];

    protected $casts = [
        'invoice_date'  => 'date',
        'invoice_value' => 'decimal:2',
        'vat_value'     => 'decimal:2',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function companyBank(): BelongsTo
    {
        return $this->belongsTo(CompanyBank::class);
    }

    public function paymentSchedules(): HasMany
    {
        return $this->hasMany(PaymentSchedule::class);
    }

    // ─── Computed ─────────────────────────────────────────────────────────────

    public function totalValue(): float
    {
        return (float) ($this->invoice_value + $this->vat_value);
    }

    public function amountAllocated(): float
    {
        return (float) $this->paymentSchedules()
            ->whereIn('status', ['paid', 'partially_paid'])
            ->sum('amount');
    }
}
