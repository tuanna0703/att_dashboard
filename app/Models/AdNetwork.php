<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdNetwork extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function briefNetworks(): HasMany
    {
        return $this->hasMany(BriefAdNetwork::class);
    }

    public function mediaBuyingOrderItems(): HasMany
    {
        return $this->hasMany(MediaBuyingOrderItem::class);
    }
}
