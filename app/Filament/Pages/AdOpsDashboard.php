<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\MyAssignedBriefsWidget;
use App\Filament\Widgets\MyPendingMBOsWidget;
use Filament\Pages\Dashboard;

class AdOpsDashboard extends Dashboard
{
    protected static string $routePath         = 'adops';
    protected static ?string $navigationIcon   = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel  = 'AdOps Dashboard';
    protected static ?string $title            = 'AdOps Dashboard';
    protected static ?string $navigationGroup  = null;
    protected static ?int $navigationSort      = 3;

    public static function canAccess(): bool
    {
        return auth()->user()->hasAnyRole(['ceo', 'coo', 'vice_ceo', 'adops', 'media_buyer']);
    }

    public function getWidgets(): array
    {
        return [
            MyAssignedBriefsWidget::class,
            MyPendingMBOsWidget::class,
        ];
    }

    public function getColumns(): int|string|array
    {
        return 1;
    }
}
