<?php

namespace App\Filament\Resources\ContractResource\RelationManagers;

use App\Filament\Resources\ReceiptResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ReceiptsRelationManager extends RelationManager
{
    protected static string $relationship = 'receipts';
    protected static ?string $title = 'Phiếu thu';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('receipt_date')
                    ->label('Ngày thu')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Số tiền')
                    ->money('VND')
                    ->weight('bold')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('payment_method')
                    ->label('Hình thức')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'bank_transfer' => 'Chuyển khoản',
                        'cash'          => 'Tiền mặt',
                        'cheque'        => 'Séc',
                        default         => $state,
                    })
                    ->colors([
                        'primary' => 'bank_transfer',
                        'success' => 'cash',
                        'warning' => 'cheque',
                    ]),

                Tables\Columns\TextColumn::make('companyBank.bank_name')
                    ->label('TK nhận')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('reference_no')
                    ->label('Mã GD')
                    ->placeholder('—')
                    ->searchable(),

                Tables\Columns\TextColumn::make('allocations_count')
                    ->label('Đợt phân bổ')
                    ->counts('allocations')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('recordedBy.name')
                    ->label('Người ghi')
                    ->placeholder('—'),
            ])
            ->defaultSort('receipt_date', 'desc')
            ->headerActions([
                Tables\Actions\Action::make('create_receipt')
                    ->label('Thêm phiếu thu')
                    ->icon('heroicon-o-plus')
                    ->url(fn () => ReceiptResource::getUrl('create')),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Xem')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => ReceiptResource::getUrl('view', ['record' => $record])),
                Tables\Actions\Action::make('edit')
                    ->label('Sửa')
                    ->icon('heroicon-o-pencil')
                    ->url(fn ($record) => ReceiptResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
