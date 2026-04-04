<?php

namespace App\Filament\Resources\BriefResource\Pages;

use App\Events\Brief\BriefCreated;
use App\Filament\Resources\BriefResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateBrief extends CreateRecord
{
    protected static string $resource = BriefResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function afterCreate(): void
    {
        event(new BriefCreated(
            subject: $this->getRecord(),
            causer:  auth()->user(),
        ));
    }
}
