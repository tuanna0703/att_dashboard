<?php

namespace App\Filament\Resources\AdNetworkResource\Pages;

use App\Filament\Resources\AdNetworkResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdNetwork extends CreateRecord
{
    protected static string $resource = AdNetworkResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
