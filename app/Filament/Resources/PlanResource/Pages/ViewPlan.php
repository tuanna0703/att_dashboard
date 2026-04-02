<?php

namespace App\Filament\Resources\PlanResource\Pages;

use App\Filament\Resources\PlanResource;
use App\Models\Plan;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPlan extends ViewRecord
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn () => in_array($this->record->status, ['draft', 're_plan'])
                    && auth()->user()->hasAnyRole(['adops', 'ceo', 'coo'])
                ),
        ];
    }
}
