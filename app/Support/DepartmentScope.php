<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Helper để áp dụng department-based scoping lên Eloquent queries.
 * Dùng trong Filament Resources::getEloquentQuery().
 */
class DepartmentScope
{
    /**
     * Scope Contract query theo role/dept của user.
     * - Contract được nhận diện qua finance_owner_id, sale_owner_id, account_owner_id.
     */
    public static function contracts(Builder $query, User $user): Builder
    {
        if ($user->canViewAll()) {
            return $query;
        }

        $deptIds = $user->getScopedDepartmentIds();

        if ($user->hasRole('vice_ceo')) {
            return $query->where(function ($q) use ($deptIds) {
                $q->whereHas('financeOwner', fn ($u) => $u->whereIn('department_id', $deptIds))
                  ->orWhereHas('saleOwner', fn ($u) => $u->whereIn('department_id', $deptIds))
                  ->orWhereHas('accountOwner', fn ($u) => $u->whereIn('department_id', $deptIds));
            });
        }

        if ($user->hasRole('finance_manager')) {
            return $query->whereHas('financeOwner', fn ($u) => $u->whereIn('department_id', $deptIds));
        }

        // finance_staff: chỉ xem contract mình được assign
        return $query->where(function ($q) use ($user) {
            $q->where('finance_owner_id', $user->id)
              ->orWhere('sale_owner_id', $user->id)
              ->orWhere('account_owner_id', $user->id);
        });
    }

    /**
     * Scope Customer query theo dept của user.
     * Customer là dữ liệu danh mục dùng chung trong phòng ban.
     * finance_manager/staff xem toàn bộ customers — không scope theo contract.
     * Chỉ Vice CEO bị giới hạn theo dept phụ trách.
     */
    public static function customers(Builder $query, User $user): Builder
    {
        if ($user->canViewAll()) {
            return $query;
        }

        $deptIds = $user->getScopedDepartmentIds();

        if ($user->hasRole('vice_ceo')) {
            return $query->whereHas('contracts', function ($c) use ($deptIds) {
                $c->where(function ($q) use ($deptIds) {
                    $q->whereHas('financeOwner', fn ($u) => $u->whereIn('department_id', $deptIds))
                      ->orWhereHas('saleOwner', fn ($u) => $u->whereIn('department_id', $deptIds));
                });
            });
        }

        // finance_manager, finance_staff: xem tất cả customers
        // Customer là danh mục dùng chung — staff cần tạo KH trước khi gắn hợp đồng
        return $query;
    }

    /**
     * Scope PaymentSchedule query theo contract.finance_owner.department_id.
     * finance_staff được xem toàn bộ trong phòng ban — responsible_user_id là field
     * operational (ai xử lý), không dùng để giới hạn visibility.
     */
    public static function paymentSchedules(Builder $query, User $user): Builder
    {
        if ($user->canViewAll()) {
            return $query;
        }

        $deptIds = $user->getScopedDepartmentIds();

        if ($user->hasRole('vice_ceo')) {
            return $query->whereHas('contract', function ($c) use ($deptIds) {
                $c->where(function ($q) use ($deptIds) {
                    $q->whereHas('financeOwner', fn ($u) => $u->whereIn('department_id', $deptIds))
                      ->orWhereHas('saleOwner', fn ($u) => $u->whereIn('department_id', $deptIds));
                });
            });
        }

        // finance_manager và finance_staff: xem theo dept qua contract.finance_owner
        // Bao gồm cả payment schedules chưa assign finance_owner (mới tạo)
        return $query->where(function ($q) use ($deptIds) {
            $q->whereHas('contract.financeOwner', fn ($u) => $u->whereIn('department_id', $deptIds))
              ->orWhereDoesntHave('contract.financeOwner');
        });
    }

    /**
     * Scope Invoice query theo contract owner.
     */
    public static function invoices(Builder $query, User $user): Builder
    {
        if ($user->canViewAll()) {
            return $query;
        }

        $deptIds = $user->getScopedDepartmentIds();

        if ($user->hasRole('vice_ceo')) {
            return $query->whereHas('contract', function ($c) use ($deptIds) {
                $c->whereHas('financeOwner', fn ($u) => $u->whereIn('department_id', $deptIds))
                  ->orWhereHas('saleOwner', fn ($u) => $u->whereIn('department_id', $deptIds));
            });
        }

        // finance_manager và finance_staff: xem theo dept, bao gồm invoice chưa assign owner
        return $query->where(function ($q) use ($deptIds) {
            $q->whereHas('contract.financeOwner', fn ($u) => $u->whereIn('department_id', $deptIds))
              ->orWhereDoesntHave('contract.financeOwner');
        });
    }

    /**
     * Scope Receipt query theo recorded_by user department.
     */
    public static function receipts(Builder $query, User $user): Builder
    {
        if ($user->canViewAll()) {
            return $query;
        }

        $deptIds = $user->getScopedDepartmentIds();

        if ($user->hasRole('vice_ceo')) {
            return $query->whereHas('recordedBy', fn ($u) => $u->whereIn('department_id', $deptIds));
        }

        // finance_manager và finance_staff: xem toàn bộ trong dept, bao gồm chưa assign
        return $query->where(function ($q) use ($deptIds) {
            $q->whereHas('recordedBy', fn ($u) => $u->whereIn('department_id', $deptIds))
              ->orWhereDoesntHave('recordedBy');
        });
    }
}
