<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** Nhân viên thuộc phòng ban này */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Vice CEO oversees phòng ban này */
    public function overseers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_overseen_departments');
    }

    /** Các vị trí trong phòng ban */
    public function positions(): HasMany
    {
        return $this->hasMany(DepartmentPosition::class);
    }

    /** Trưởng phòng */
    public function head(): HasMany
    {
        return $this->hasMany(DepartmentPosition::class)->where('position', 'head')->whereNull('left_at');
    }

    /** Nhân sự đang hoạt động trong phòng ban */
    public function activePositions(): HasMany
    {
        return $this->hasMany(DepartmentPosition::class)->whereNull('left_at');
    }
}
