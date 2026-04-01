<?php

namespace App\Filament\Resources\AdNetworkResource\Pages;

use App\Filament\Resources\AdNetworkResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAdNetwork extends EditRecord
{
    protected static string $resource = AdNetworkResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
