<?php

namespace App\Filament\Resources\MediaBuyingOrderResource\Pages;

use App\Filament\Resources\MediaBuyingOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMediaBuyingOrders extends ListRecords
{
    protected static string $resource = MediaBuyingOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('+ Tạo MBO')];
    }
}
