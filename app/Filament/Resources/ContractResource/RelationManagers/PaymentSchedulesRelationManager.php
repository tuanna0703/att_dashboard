<?php

namespace App\Filament\Resources\ContractResource\RelationManagers;

use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentSchedulesRelationManager extends RelationManager
{
    protected static string $relationship = 'paymentSchedules';
    protected static ?string $title = 'Lịch thanh toán';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('installment_no')
                ->label('Đợt số')
                ->numeric()
                ->required(),
            Forms\Components\Select::make('schedule_type')
                ->label('Loại đợt')
                ->options([
                    'advance'      => 'Tạm ứng',
                    'milestone'    => 'Milestone',
                    'acceptance'   => 'Nghiệm thu',
                    'subscription' => 'Thuê bao',
                ])
                ->required(),
            Forms\Components\TextInput::make('amount')
                ->label('Số tiền')
                ->numeric()
                ->prefix('VND')
                ->required(),
            Forms\Components\TextInput::make('vat_amount')
                ->label('Tiền VAT')
                ->numeric()
                ->prefix('VND')
                ->default(0),
            Forms\Components\DatePicker::make('due_date')
                ->label('Hạn thanh toán')
                ->required(),
            Forms\Components\DatePicker::make('invoice_expected_date')
                ->label('Dự kiến xuất HĐ'),
            Forms\Components\Select::make('responsible_user_id')
                ->label('Người phụ trách')
                ->options(User::pluck('name', 'id'))
                ->searchable(),
            Forms\Components\Select::make('status')
                ->label('Trạng thái')
                ->options([
                    'pending'         => 'Chờ xử lý',
                    'invoiced'        => 'Đã xuất HĐ',
                    'partially_paid'  => 'Thu một phần',
                    'paid'            => 'Đã thu',
                    'overdue'         => 'Quá hạn',
                ])
                ->default('pending'),
            Forms\Components\Textarea::make('note')->label('Ghi chú')->columnSpan(2),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('installment_no')->label('Đợt')->alignCenter()->sortable(),
                Tables\Columns\BadgeColumn::make('schedule_type')
                    ->label('Loại')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'advance'      => 'Tạm ứng',
                        'milestone'    => 'Milestone',
                        'acceptance'   => 'Nghiệm thu',
                        'subscription' => 'Thuê bao',
                        default        => $state,
                    }),
                Tables\Columns\TextColumn::make('amount')->label('Số tiền')->money('VND')->sortable(),
                Tables\Columns\TextColumn::make('vat_amount')->label('VAT')->money('VND'),
                Tables\Columns\TextColumn::make('due_date')->label('Hạn TT')->date('d/m/Y')->sortable()
                    ->color(fn ($record) => $record?->isOverdue() ? 'danger' : null),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Trạng thái')
                    ->colors([
                        'gray'    => 'pending',
                        'primary' => 'invoiced',
                        'warning' => 'partially_paid',
                        'success' => 'paid',
                        'danger'  => 'overdue',
                    ])
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'pending'        => 'Chờ',
                        'invoiced'       => 'Đã HĐ',
                        'partially_paid' => 'Thu 1 phần',
                        'paid'           => 'Đã thu',
                        'overdue'        => 'Quá hạn',
                        default          => $state,
                    }),
                Tables\Columns\TextColumn::make('responsibleUser.name')->label('Phụ trách'),
            ])
            ->defaultSort('installment_no')
            ->headerActions([Tables\Actions\CreateAction::make()])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
