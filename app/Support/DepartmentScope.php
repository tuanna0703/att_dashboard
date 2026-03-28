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
     * Customer được liên kết qua contracts.finance_owner.
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

        if ($user->hasRole('finance_manager')) {
            return $query->whereHas('contracts.financeOwner', fn ($u) => $u->whereIn('department_id', $deptIds));
        }

        // staff: khách hàng có contract mình được assign
        return $query->whereHas('contracts', function ($c) use ($user) {
            $c->where('finance_owner_id', $user->id)
              ->orWhere('sale_owner_id', $user->id)
              ->orWhere('account_owner_id', $user->id);
        });
    }

    /**
     * Scope PaymentSchedule query theo responsible_user.department_id.
     */
    public static function paymentSchedules(Builder $query, User $user): Builder
    {
        if ($user->canViewAll()) {
            return $query;
        }

        $deptIds = $user->getScopedDepartmentIds();

        if ($user->hasRole('vice_ceo') || $user->hasRole('finance_manager')) {
            return $query->whereHas('responsibleUser', fn ($u) => $u->whereIn('department_id', $deptIds));
        }

        // staff: chỉ xem schedule mình phụ trách
        return $query->where('responsible_user_id', $user->id);
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

        if ($user->hasRole('finance_manager')) {
            return $query->whereHas('contract.financeOwner', fn ($u) => $u->whereIn('department_id', $deptIds));
        }

        return $query->whereHas('contract', function ($c) use ($user) {
            $c->where('finance_owner_id', $user->id)
              ->orWhere('sale_owner_id', $user->id);
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

        if ($user->hasRole('vice_ceo') || $user->hasRole('finance_manager')) {
            return $query->whereHas('recordedBy', fn ($u) => $u->whereIn('department_id', $deptIds));
        }

        return $query->where('recorded_by', $user->id);
    }
}
