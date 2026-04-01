<?php

namespace App\Filament\Resources\BookingResource\RelationManagers;

use App\Models\MediaBuyingOrder;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Form;

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
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Trạng thái')
                    ->formatStateUsing(fn ($state) => MediaBuyingOrder::$statuses[$state] ?? $state)
                    ->colors(MediaBuyingOrder::$statusColors),
                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('AdOps'),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Xem')
                    ->icon('heroicon-o-eye')
                    ->url(fn (MediaBuyingOrder $record) => route('filament.admin.resources.media-buying-orders.view', $record)),
            ]);
    }
}
