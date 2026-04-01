<?php

namespace App\Filament\Resources\AdNetworkResource\Pages;

use App\Filament\Resources\AdNetworkResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAdNetworks extends ListRecords
{
    protected static string $resource = AdNetworkResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
