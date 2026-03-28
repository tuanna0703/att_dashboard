<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentSchedule extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'contract_id',
        'invoice_id',
        'installment_no',
        'schedule_type',
        'amount',
        'vat_amount',
        'due_date',
        'invoice_expected_date',
        'invoice_issued_date',
        'responsible_user_id',
        'status',
        'note',
    ];

    protected $casts = [
        'due_date'              => 'date',
        'invoice_expected_date' => 'date',
        'invoice_issued_date'   => 'date',
        'amount'                => 'decimal:2',
        'vat_amount'            => 'decimal:2',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function receiptAllocations(): HasMany
    {
        return $this->hasMany(ReceiptAllocation::class);
    }

    // ─── Computed ─────────────────────────────────────────────────────────────

    public function totalWithVat(): float
    {
        return (float) ($this->amount + $this->vat_amount);
    }

    public function amountReceived(): float
    {
        return (float) $this->receiptAllocations()->sum('allocated_amount');
    }

    public function amountRemaining(): float
    {
        return (float) max(0, $this->totalWithVat() - $this->amountReceived());
    }

    public function isOverdue(): bool
    {
        return ! in_array($this->status, ['paid'])
            && $this->due_date !== null
            && $this->due_date->isPast();
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue');
    }

    public function scopeDueSoon($query, int $days = 7)
    {
        return $query->whereIn('status', ['pending', 'invoiced'])
            ->whereBetween('due_date', [now(), now()->addDays($days)]);
    }

    public function scopeUnpaid($query)
    {
        return $query->whereNotIn('status', ['paid', 'cancelled']);
    }
}
