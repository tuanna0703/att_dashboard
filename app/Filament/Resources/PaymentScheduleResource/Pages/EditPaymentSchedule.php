<?php

namespace App\Filament\Resources\PaymentScheduleResource\Pages;

use App\Filament\Resources\PaymentScheduleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPaymentSchedule extends EditRecord
{
    protected static string $resource = PaymentScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
