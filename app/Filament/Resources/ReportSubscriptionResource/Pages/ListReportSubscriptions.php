<?php

namespace App\Filament\Resources\ReportSubscriptionResource\Pages;

use App\Filament\Resources\ReportSubscriptionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListReportSubscriptions extends ListRecords
{
    protected static string $resource = ReportSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
