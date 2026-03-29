<?php

namespace App\Filament\Widgets;

use App\Models\PaymentSchedule;
use App\Support\DepartmentScope;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CashflowStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    protected static bool $isDiscovered = false;
    protected static ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $user = auth()->user();

        $base = fn () => DepartmentScope::paymentSchedules(PaymentSchedule::query(), $user);

        $ar = $base()
            ->whereIn('status', ['pending', 'invoiced', 'partially_paid', 'overdue'])
            ->sum('amount');

        $overdueItems = $base()->where('status', 'overdue')->selectRaw('COUNT(*) as cnt, SUM(amount) as total')->first();
        $overdue      = (float) ($overdueItems->total ?? 0);
        $overdueCount = (int) ($overdueItems->cnt ?? 0);

        $dueMonth = $base()
            ->whereIn('status', ['pending', 'invoiced', 'partially_paid', 'overdue'])
            ->whereBetween('due_date', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');

        $forecast = $base()
            ->whereIn('status', ['pending', 'invoiced'])
            ->whereBetween('due_date', [today(), today()->addDays(30)])
            ->sum('amount');

        $dueSoonCount = $base()
            ->whereIn('status', ['pending', 'invoiced'])
            ->whereBetween('due_date', [today(), today()->addDays(7)])
            ->count();

        return [
            Stat::make('Tổng AR', number_format((float) $ar, 0, ',', '.') . ' ₫')
                ->description('Công nợ chưa thu — Xem chi tiết')
                ->color('primary')
                ->icon('heroicon-o-banknotes')
                ->url('/admin/payment-schedules'),

            Stat::make('Tổng quá hạn', number_format($overdue, 0, ',', '.') . ' ₫')
                ->description("{$overdueCount} đợt cần xử lý — Xem danh sách")
                ->color('danger')
                ->icon('heroicon-o-exclamation-triangle')
                ->url('/admin/payment-schedules?tableFilters[status][value]=overdue'),

            Stat::make('Đến hạn tháng ' . now()->format('m'), number_format((float) $dueMonth, 0, ',', '.') . ' ₫')
                ->description('Trong tháng này — Xem chi tiết')
                ->color('warning')
                ->icon('heroicon-o-calendar-days')
                ->url('/admin/payment-schedules?tableFilters[due_this_month][isActive]=true'),

            Stat::make('Dự báo 30 ngày tới', number_format((float) $forecast, 0, ',', '.') . ' ₫')
                ->description("{$dueSoonCount} đợt đến hạn trong 7 ngày")
                ->color('success')
                ->icon('heroicon-o-arrow-trending-up')
                ->url('/admin/payment-schedules'),
        ];
    }
}
