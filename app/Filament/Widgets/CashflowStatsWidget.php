<?php

namespace App\Filament\Widgets;

use App\Models\CashflowSnapshot;
use App\Services\CollectionService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CashflowStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    protected static bool $isDiscovered = false;
    protected static ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $snapshot = CashflowSnapshot::whereDate('snapshot_date', today())->first();

        if ($snapshot) {
            $ar           = $snapshot->total_ar;
            $overdue      = $snapshot->total_overdue;
            $dueMonth     = $snapshot->due_this_month;
            $forecast     = $snapshot->forecast_30_days;
            $overdueCount = $snapshot->overdue_count;
            $dueSoonCount = $snapshot->due_soon_count;
        } else {
            /** @var CollectionService $service */
            $service      = app(CollectionService::class);
            $ar           = $service->totalAR();
            $overdueList  = $service->overdueList();
            $overdue      = $overdueList->sum('amount');
            $overdueCount = $overdueList->count();
            $dueMonth     = $service->dueThisMonth();
            $forecast     = $service->forecastNext30Days();
            $dueSoonCount = $service->dueSoon(7)->count();
        }

        return [
            Stat::make('Tổng AR', number_format((float) $ar, 0, ',', '.') . ' ₫')
                ->description('Công nợ chưa thu')
                ->color('primary')
                ->icon('heroicon-o-banknotes'),

            Stat::make('Tổng quá hạn', number_format((float) $overdue, 0, ',', '.') . ' ₫')
                ->description("{$overdueCount} đợt cần xử lý")
                ->color('danger')
                ->icon('heroicon-o-exclamation-triangle'),

            Stat::make('Đến hạn tháng ' . now()->format('m'), number_format((float) $dueMonth, 0, ',', '.') . ' ₫')
                ->color('warning')
                ->icon('heroicon-o-calendar-days'),

            Stat::make('Dự báo 30 ngày tới', number_format((float) $forecast, 0, ',', '.') . ' ₫')
                ->description("{$dueSoonCount} đợt đến hạn trong 7 ngày")
                ->color('success')
                ->icon('heroicon-o-arrow-trending-up'),
        ];
    }
}
