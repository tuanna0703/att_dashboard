<?php

namespace App\Filament\Resources\MediaBuyingOrderResource\Pages;

use App\Filament\Resources\MediaBuyingOrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMediaBuyingOrder extends CreateRecord
{
    protected static string $resource = MediaBuyingOrderResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
