<?php

namespace App\Filament\Resources\BriefResource\Pages;

use App\Filament\Resources\BriefResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBrief extends CreateRecord
{
    protected static string $resource = BriefResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
