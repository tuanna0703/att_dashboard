<?php

namespace App\Filament\Resources\MediaBuyingOrderResource\Pages;

use App\Filament\Resources\MediaBuyingOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMediaBuyingOrder extends EditRecord
{
    protected static string $resource = MediaBuyingOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->status === 'draft'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
