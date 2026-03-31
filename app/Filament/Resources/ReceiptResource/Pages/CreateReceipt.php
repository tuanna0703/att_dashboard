<?php

namespace App\Filament\Resources\ReceiptResource\Pages;

use App\Filament\Resources\ReceiptResource;
use Filament\Resources\Pages\CreateRecord;

class CreateReceipt extends CreateRecord
{
    protected static string $resource = ReceiptResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // amount sẽ được Observer tính lại từ tổng allocations sau khi lưu
        $data['amount'] = 0;
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
