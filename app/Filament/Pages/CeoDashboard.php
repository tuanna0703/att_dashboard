<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CashflowStatsWidget;
use App\Filament\Widgets\TopDebtorsWidget;
use Filament\Pages\Dashboard;

class CeoDashboard extends Dashboard
{
    protected static ?string $navigationIcon  = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'CEO Dashboard';
    protected static ?string $title           = 'CEO Dashboard';
    protected static ?int $navigationSort     = 1;

    public function getWidgets(): array
    {
        return [
            CashflowStatsWidget::class,
            TopDebtorsWidget::class,
        ];
    }

    public function getColumns(): int|string|array
    {
        return 2;
    }
}
