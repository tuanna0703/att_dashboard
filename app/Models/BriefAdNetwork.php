<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BriefAdNetwork extends Model
{
    protected $fillable = [
        'brief_id',
        'ad_network_id',
        'screen_count',
        'note',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function brief(): BelongsTo
    {
        return $this->belongsTo(Brief::class);
    }

    public function adNetwork(): BelongsTo
    {
        return $this->belongsTo(AdNetwork::class);
    }
}
