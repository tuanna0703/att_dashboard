<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\PaymentSchedule;
use Illuminate\Support\Carbon;

class CollectionService
{
    /**
     * Summary of all unpaid schedules for a customer.
     */
    public function customerSummary(Customer $customer): array
    {
        $schedules = PaymentSchedule::query()
            ->whereHas('contract', fn ($q) => $q->where('customer_id', $customer->id))
            ->get();

        return [
            'total_outstanding' => $schedules->whereIn('status', ['pending', 'invoiced', 'partially_paid', 'overdue'])->sum('amount'),
            'total_overdue'     => $schedules->where('status', 'overdue')->sum('amount'),
            'total_paid'        => $schedules->where('status', 'paid')->sum('amount'),
            'overdue_count'     => $schedules->where('status', 'overdue')->count(),
        ];
    }

    /**
     * Schedules due within N days (for dashboard "due soon" widget).
     */
    public function dueSoon(int $days = 7): \Illuminate\Database\Eloquent\Collection
    {
        return PaymentSchedule::with(['contract.customer', 'responsibleUser'])
            ->whereIn('status', ['pending', 'invoiced'])
            ->whereBetween('due_date', [Carbon::today(), Carbon::today()->addDays($days)])
            ->orderBy('due_date')
            ->get();
    }

    /**
     * All overdue schedules ordered by days overdue descending.
     */
    public function overdueList(): \Illuminate\Database\Eloquent\Collection
    {
        return PaymentSchedule::with(['contract.customer', 'responsibleUser'])
            ->where('status', 'overdue')
            ->orderBy('due_date')
            ->get();
    }

    /**
     * Top N customers by outstanding amount.
     */
    public function topDebtors(int $limit = 10): \Illuminate\Support\Collection
    {
        return PaymentSchedule::query()
            ->selectRaw('customers.id, customers.name, SUM(payment_schedules.amount) as outstanding')
            ->join('contracts', 'payment_schedules.contract_id', '=', 'contracts.id')
            ->join('customers', 'contracts.customer_id', '=', 'customers.id')
            ->whereIn('payment_schedules.status', ['pending', 'invoiced', 'partially_paid', 'overdue'])
            ->whereNull('payment_schedules.deleted_at')
            ->whereNull('contracts.deleted_at')
            ->groupBy('customers.id', 'customers.name')
            ->orderByDesc('outstanding')
            ->limit($limit)
            ->get();
    }

    /**
     * Total AR (Accounts Receivable) — all unpaid schedules.
     */
    public function totalAR(): float
    {
        return (float) PaymentSchedule::whereIn('status', ['pending', 'invoiced', 'partially_paid', 'overdue'])
            ->sum('amount');
    }

    /**
     * Amount due within current month.
     */
    public function dueThisMonth(): float
    {
        return (float) PaymentSchedule::whereIn('status', ['pending', 'invoiced', 'partially_paid', 'overdue'])
            ->whereBetween('due_date', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])
            ->sum('amount');
    }

    /**
     * Forecast: expected collections in the next 30 days.
     */
    public function forecastNext30Days(): float
    {
        return (float) PaymentSchedule::whereIn('status', ['pending', 'invoiced'])
            ->whereBetween('due_date', [Carbon::today(), Carbon::today()->addDays(30)])
            ->sum('amount');
    }
}
