<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

class DepartmentPosition extends Model
{
    protected $fillable = [
        'department_id',
        'user_id',
        'position',
        'role_id',
        'is_primary',
        'joined_at',
        'left_at',
        'note',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'joined_at'  => 'date',
        'left_at'    => 'date',
    ];

    public static array $positions = [
        'head'        => 'Trưởng phòng',
        'deputy_head' => 'Phó phòng',
        'member'      => 'Nhân viên',
    ];

    public static array $positionColors = [
        'head'        => 'danger',
        'deputy_head' => 'warning',
        'member'      => 'gray',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->whereNull('left_at');
    }

    public function scopeHeads($query)
    {
        return $query->where('position', 'head');
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }
}
