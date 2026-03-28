<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ContractsRelationManager extends RelationManager
{
    protected static string $relationship = 'contracts';
    protected static ?string $title = 'Hợp đồng';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('contract_code')
                    ->label('Số HĐ')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('contract_type')
                    ->label('Loại')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'ads'          => 'Quảng cáo',
                        'project'      => 'Dự án',
                        'subscription' => 'Thuê bao',
                        default        => $state,
                    })
                    ->badge(),
                Tables\Columns\TextColumn::make('total_value_estimated')
                    ->label('Giá trị (est.)')
                    ->money('VND')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Trạng thái')
                    ->colors([
                        'gray'    => 'draft',
                        'success' => 'active',
                        'primary' => 'completed',
                        'danger'  => 'cancelled',
                    ])
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'draft'     => 'Nháp',
                        'active'    => 'Đang chạy',
                        'completed' => 'Hoàn thành',
                        'cancelled' => 'Huỷ',
                        default     => $state,
                    }),
                Tables\Columns\TextColumn::make('signed_date')
                    ->label('Ngày ký')
                    ->date('d/m/Y'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('contract_code')->label('Số HĐ')->required(),
            Forms\Components\Select::make('contract_type')
                ->label('Loại hợp đồng')
                ->options(['ads' => 'Quảng cáo', 'project' => 'Dự án', 'subscription' => 'Thuê bao'])
                ->required(),
            Forms\Components\TextInput::make('total_value_estimated')
                ->label('Giá trị ước tính')
                ->numeric()
                ->prefix('VND'),
            Forms\Components\DatePicker::make('signed_date')->label('Ngày ký'),
        ]);
    }
}
