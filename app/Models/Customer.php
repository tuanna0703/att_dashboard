<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'tax_code',
        'address',
        'contact_name',
        'contact_email',
        'contact_phone',
        'credit_rating',
        'status',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class)->orderByDesc('is_primary')->orderBy('name');
    }

    // ─── Computed / Aggregates ────────────────────────────────────────────────

    public function totalOutstanding(): float
    {
        return $this->contracts()
            ->with('paymentSchedules')
            ->get()
            ->flatMap(fn ($c) => $c->paymentSchedules)
            ->whereIn('status', ['pending', 'invoiced', 'partially_paid', 'overdue'])
            ->sum('amount');
    }

    public function totalOverdue(): float
    {
        return $this->contracts()
            ->with('paymentSchedules')
            ->get()
            ->flatMap(fn ($c) => $c->paymentSchedules)
            ->where('status', 'overdue')
            ->sum('amount');
    }
}
