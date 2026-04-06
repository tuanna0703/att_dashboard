<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    protected $fillable = [
        'name',
        'email',
        'password',
        'department_id',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** Các phòng ban Vice CEO này oversee */
    public function overseenDepartments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'user_overseen_departments');
    }

    /** Vị trí trong các phòng ban */
    public function departmentPositions(): HasMany
    {
        return $this->hasMany(DepartmentPosition::class);
    }

    /** Phòng ban chính (is_primary) */
    public function primaryPosition(): HasMany
    {
        return $this->hasMany(DepartmentPosition::class)->where('is_primary', true)->whereNull('left_at');
    }

    /** Kiểm tra user có phải trưởng phòng của dept nào không */
    public function isDeptHead(?int $departmentId = null): bool
    {
        $query = $this->departmentPositions()->where('position', 'head')->whereNull('left_at');
        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }
        return $query->exists();
    }

    /** Lấy danh sách user IDs cùng phòng ban (dùng cho scoping) */
    public function getManagedUserIds(): array
    {
        $deptIds = $this->departmentPositions()
            ->whereIn('position', ['head', 'deputy_head'])
            ->whereNull('left_at')
            ->pluck('department_id');

        if ($deptIds->isEmpty()) {
            return [$this->id];
        }

        return DepartmentPosition::whereIn('department_id', $deptIds)
            ->whereNull('left_at')
            ->pluck('user_id')
            ->unique()
            ->values()
            ->toArray();
    }

    public function contractsAsSale(): HasMany
    {
        return $this->hasMany(Contract::class, 'sale_owner_id');
    }

    public function contractsAsAccount(): HasMany
    {
        return $this->hasMany(Contract::class, 'account_owner_id');
    }

    public function contractsAsFinance(): HasMany
    {
        return $this->hasMany(Contract::class, 'finance_owner_id');
    }

    public function paymentSchedules(): HasMany
    {
        return $this->hasMany(PaymentSchedule::class, 'responsible_user_id');
    }

    public function receiptsRecorded(): HasMany
    {
        return $this->hasMany(Receipt::class, 'recorded_by');
    }

    // ─── Permission Helpers ───────────────────────────────────────────────────

    /** CEO và COO xem toàn bộ dữ liệu */
    public function canViewAll(): bool
    {
        return $this->hasAnyRole(['ceo', 'coo']);
    }

    /**
     * Trả về mảng department_id user được phép xem.
     * null = không giới hạn (CEO/COO)
     * []   = không có dept (cần xử lý riêng)
     *
     * @return int[]|null
     */
    public function getScopedDepartmentIds(): ?array
    {
        if ($this->canViewAll()) {
            return null; // null = all
        }

        if ($this->hasRole('vice_ceo')) {
            return $this->overseenDepartments()->pluck('departments.id')->toArray();
        }

        // finance_manager, finance_staff, department_manager...
        return $this->department_id ? [$this->department_id] : [];
    }

    /** User chỉ được xem data của chính mình */
    public function isStaffLevel(): bool
    {
        return !$this->hasAnyRole(['ceo', 'coo', 'vice_ceo', 'finance_manager']);
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
