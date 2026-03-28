<?php

namespace App\Filament\Resources\ContractResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ContractLinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';
    protected static ?string $title = 'Hạng mục hợp đồng';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Hạng mục')
                ->required()
                ->columnSpan(2),
            Forms\Components\TextInput::make('planned_value')
                ->label('Giá trị kế hoạch')
                ->numeric()
                ->prefix('VND')
                ->required(),
            Forms\Components\TextInput::make('actual_value')
                ->label('Giá trị thực tế')
                ->numeric()
                ->prefix('VND')
                ->default(0),
            Forms\Components\TextInput::make('vat_rate')
                ->label('VAT (%)')
                ->numeric()
                ->default(10)
                ->suffix('%'),
            Forms\Components\DatePicker::make('start_date')->label('Từ ngày'),
            Forms\Components\DatePicker::make('end_date')->label('Đến ngày'),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Hạng mục')->searchable(),
                Tables\Columns\TextColumn::make('planned_value')->label('KH')->money('VND'),
                Tables\Columns\TextColumn::make('actual_value')->label('Thực tế')->money('VND'),
                Tables\Columns\TextColumn::make('vat_rate')->label('VAT')->suffix('%'),
                Tables\Columns\TextColumn::make('start_date')->label('Từ')->date('d/m/Y'),
                Tables\Columns\TextColumn::make('end_date')->label('Đến')->date('d/m/Y'),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
