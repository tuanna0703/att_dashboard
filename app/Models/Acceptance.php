<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Acceptance extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'contract_id',
        'acceptance_no',
        'acceptance_date',
        'accepted_value',
        'file_path',
        'status',
        'note',
    ];

    protected $casts = [
        'acceptance_date' => 'date',
        'accepted_value'  => 'decimal:2',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
