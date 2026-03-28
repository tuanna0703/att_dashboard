<?php

namespace App\Filament\Resources\ReceiptResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AllocationsRelationManager extends RelationManager
{
    protected static string $relationship = 'allocations';
    protected static ?string $title = 'Phân bổ vào lịch thanh toán';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('payment_schedule_id')
                ->label('Đợt thanh toán')
                ->relationship(
                    'paymentSchedule',
                    'installment_no',
                    fn ($query) => $query->whereNotIn('status', ['paid'])->with('contract')
                )
                ->getOptionLabelFromRecordUsing(
                    fn ($record) => "[{$record->contract->contract_code}] Đợt {$record->installment_no} - " .
                        number_format($record->amount) . ' VND - HH: ' . $record->due_date->format('d/m/Y')
                )
                ->searchable()
                ->required(),
            Forms\Components\TextInput::make('allocated_amount')
                ->label('Số tiền phân bổ')
                ->numeric()
                ->prefix('VND')
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('paymentSchedule.contract.contract_code')
                    ->label('Hợp đồng'),
                Tables\Columns\TextColumn::make('paymentSchedule.contract.customer.name')
                    ->label('Khách hàng'),
                Tables\Columns\TextColumn::make('paymentSchedule.installment_no')
                    ->label('Đợt')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('paymentSchedule.due_date')
                    ->label('Hạn TT')
                    ->date('d/m/Y'),
                Tables\Columns\TextColumn::make('allocated_amount')
                    ->label('Số tiền phân bổ')
                    ->money('VND')
                    ->weight('bold'),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ]);
    }
}
