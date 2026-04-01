<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CampaignPipelineWidget;
use App\Filament\Widgets\CashflowStatsWidget;
use App\Filament\Widgets\TopDebtorsWidget;
use Filament\Pages\Dashboard;

class CeoDashboard extends Dashboard
{
    protected static ?string $navigationIcon  = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'CEO Dashboard';
    protected static ?string $title           = 'CEO Dashboard';
    protected static ?int $navigationSort     = 1;

    public static function canAccess(): bool
    {
        return auth()->user()->hasAnyRole(['ceo', 'coo']);
    }

    public function getWidgets(): array
    {
        return [
            CampaignPipelineWidget::class,
            CashflowStatsWidget::class,
            TopDebtorsWidget::class,
        ];
    }

    public function getColumns(): int|string|array
    {
        return 2;
    }
}
