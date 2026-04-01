<?php

namespace App\Filament\Resources\BriefResource\Pages;

use App\Filament\Resources\BriefResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBriefs extends ListRecords
{
    protected static string $resource = BriefResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('+ Tạo Brief')];
    }
}
