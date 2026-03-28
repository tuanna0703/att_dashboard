<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerContact extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'customer_id',
        'name',
        'title',
        'role',
        'phone',
        'email',
        'is_primary',
        'note',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public static array $roles = [
        'management' => 'Quản lý / Sếp',
        'contract'   => 'Phụ trách hợp đồng',
        'booking'    => 'Phụ trách booking',
        'payment'    => 'Phụ trách thanh toán & hóa đơn',
        'other'      => 'Khác',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function getRoleLabelAttribute(): string
    {
        return self::$roles[$this->role] ?? $this->role;
    }
}
