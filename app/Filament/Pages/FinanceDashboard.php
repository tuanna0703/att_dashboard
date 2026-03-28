<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CashflowStatsWidget;
use App\Filament\Widgets\DueSoonWidget;
use App\Filament\Widgets\OverdueListWidget;
use Filament\Pages\Dashboard;

class FinanceDashboard extends Dashboard
{
    protected static string $routePath         = 'finance';
    protected static ?string $navigationIcon   = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel  = 'Finance Dashboard';
    protected static ?string $title            = 'Finance Dashboard';
    protected static ?int $navigationSort      = 2;

    public function getWidgets(): array
    {
        return [
            CashflowStatsWidget::class,
            OverdueListWidget::class,
            DueSoonWidget::class,
        ];
    }

    public function getColumns(): int|string|array
    {
        return 1;
    }
}
