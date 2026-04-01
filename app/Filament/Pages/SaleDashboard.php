<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CampaignPipelineWidget;
use App\Filament\Widgets\MyBriefsStatsWidget;
use Filament\Pages\Dashboard;

class SaleDashboard extends Dashboard
{
    protected static string $routePath         = 'sale';
    protected static ?string $navigationIcon   = 'heroicon-o-briefcase';
    protected static ?string $navigationLabel  = 'Sale Dashboard';
    protected static ?string $title            = 'Sale Dashboard';
    protected static ?int $navigationSort      = 4;

    public static function canAccess(): bool
    {
        return auth()->user()->hasAnyRole(['ceo', 'coo', 'vice_ceo', 'sale']);
    }

    public function getWidgets(): array
    {
        return [
            MyBriefsStatsWidget::class,
            CampaignPipelineWidget::class,
        ];
    }

    public function getColumns(): int|string|array
    {
        return 2;
    }
}
