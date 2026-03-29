<?php

namespace App\Filament\Resources\ReportSubscriptionResource\Pages;

use App\Filament\Resources\ReportSubscriptionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditReportSubscription extends EditRecord
{
    protected static string $resource = ReportSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
