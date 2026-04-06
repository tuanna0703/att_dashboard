<?php

namespace App\Filament\Resources\DepartmentResource\Pages;

use App\Filament\Resources\DepartmentResource;
use App\Filament\Resources\DepartmentResource\RelationManagers\PositionsRelationManager;
use Filament\Actions;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewDepartment extends ViewRecord
{
    protected static string $resource = DepartmentResource::class;

    public function getRelationManagers(): array
    {
        return [
            PositionsRelationManager::class,
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Thông tin phòng ban')->schema([
                TextEntry::make('code')
                    ->label('Mã phòng ban')
                    ->weight('bold'),
                TextEntry::make('name')
                    ->label('Tên phòng ban'),
                IconEntry::make('is_active')
                    ->label('Hoạt động')
                    ->boolean(),
                TextEntry::make('description')
                    ->label('Mô tả')
                    ->placeholder('—')
                    ->columnSpanFull(),
            ])->columns(3),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
