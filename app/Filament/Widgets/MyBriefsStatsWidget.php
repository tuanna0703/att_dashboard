<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use App\Models\Brief;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MyBriefsStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    protected static bool $isDiscovered = false;
    protected static ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $userId = auth()->id();

        $drafts = Brief::where('sale_id', $userId)->where('status', 'draft')->count();
        $inProgress = Brief::where('sale_id', $userId)
            ->whereIn('status', ['sent_to_adops', 'planning_ready', 'sent_to_customer', 'customer_feedback'])
            ->count();
        $confirmed = Brief::where('sale_id', $userId)->where('status', 'confirmed')->count();
        $activeBookings = Booking::where('sale_id', $userId)
            ->whereIn('status', ['contract_signed', 'campaign_active'])
            ->count();

        return [
            Stat::make('Brief nháp', $drafts)
                ->description('Chưa gửi AdOps')
                ->color('gray')
                ->icon('heroicon-o-document')
                ->url('/admin/briefs?tableFilters[status][value]=draft'),

            Stat::make('Brief đang xử lý', $inProgress)
                ->description('Đang planning / chờ KH')
                ->color('info')
                ->icon('heroicon-o-arrow-path')
                ->url('/admin/briefs'),

            Stat::make('KH đã confirm', $confirmed)
                ->description('Chờ tạo Booking')
                ->color('success')
                ->icon('heroicon-o-check-badge')
                ->url('/admin/briefs?tableFilters[status][value]=confirmed'),

            Stat::make('Campaign đang chạy', $activeBookings)
                ->description('Booking đang active')
                ->color('warning')
                ->icon('heroicon-o-play')
                ->url('/admin/bookings'),
        ];
    }
}
