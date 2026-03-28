<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContractLine extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'contract_id',
        'name',
        'planned_value',
        'actual_value',
        'vat_rate',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'planned_value' => 'decimal:2',
        'actual_value'  => 'decimal:2',
        'vat_rate'      => 'decimal:2',
        'start_date'    => 'date',
        'end_date'      => 'date',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    // ─── Computed ─────────────────────────────────────────────────────────────

    public function vatAmount(): float
    {
        return (float) ($this->actual_value * $this->vat_rate / 100);
    }

    public function totalWithVat(): float
    {
        return (float) ($this->actual_value + $this->vatAmount());
    }
}
