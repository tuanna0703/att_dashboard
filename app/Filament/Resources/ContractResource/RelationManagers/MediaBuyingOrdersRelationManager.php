<?php

namespace App\Filament\Resources\ContractResource\RelationManagers;

use App\Models\MediaBuyingOrder;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MediaBuyingOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'mediaBuyingOrders';
    protected static ?string $title = 'Media Buying Orders';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('order_no')
            ->columns([
                Tables\Columns\TextColumn::make('order_no')
                    ->label('Mã MBO')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Tổng tiền')
                    ->money('VND'),

                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('AdOps')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('buyer.name')
                    ->label('Buyer')
                    ->placeholder('—'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Trạng thái')
                    ->formatStateUsing(fn ($state) => MediaBuyingOrder::$statuses[$state] ?? $state)
                    ->colors(MediaBuyingOrder::$statusColors),
            ])
            ->headerActions([
                Tables\Actions\Action::make('create_mbo')
                    ->label('+ Tạo MBO')
                    ->icon('heroicon-o-plus')
                    ->url(fn () => \App\Filament\Resources\MediaBuyingOrderResource::getUrl('create', [
                        'contract_id' => $this->getOwnerRecord()->id,
                    ])),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Xem')
                    ->icon('heroicon-o-eye')
                    ->url(fn (MediaBuyingOrder $record) => \App\Filament\Resources\MediaBuyingOrderResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
