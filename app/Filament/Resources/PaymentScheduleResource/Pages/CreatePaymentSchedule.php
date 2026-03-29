<?php

namespace App\Filament\Resources\PaymentScheduleResource\Pages;

use App\Filament\Resources\PaymentScheduleResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePaymentSchedule extends CreateRecord
{
    protected static string $resource = PaymentScheduleResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
