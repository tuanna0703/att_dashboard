<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Receipt extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'receipt_date',
        'amount',
        'payment_method',
        'reference_no',
        'bank_account',
        'note',
        'recorded_by',
    ];

    protected $casts = [
        'receipt_date' => 'date',
        'amount'       => 'decimal:2',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ReceiptAllocation::class);
    }

    // ─── Computed ─────────────────────────────────────────────────────────────

    public function amountAllocated(): float
    {
        return (float) $this->allocations()->sum('allocated_amount');
    }

    public function amountUnallocated(): float
    {
        return (float) max(0, $this->amount - $this->amountAllocated());
    }
}
