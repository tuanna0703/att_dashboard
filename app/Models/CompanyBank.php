<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyBank extends Model
{
    protected $fillable = [
        'bank_name',
        'account_number',
        'account_name',
        'branch',
        'is_default',
        'note',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function getDisplayNameAttribute(): string
    {
        $label = "{$this->bank_name} — {$this->account_number} ({$this->account_name})";
        if ($this->branch) {
            $label .= " — {$this->branch}";
        }
        return $label;
    }
}
