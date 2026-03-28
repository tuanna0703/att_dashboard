<?php

namespace App\Filament\Widgets;

use App\Models\PaymentSchedule;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Carbon;

class OverdueListWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    protected static bool $isDiscovered = false;
    protected int|string|array $columnSpan = 'full';
    protected static ?string $heading = 'Danh sách quá hạn';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                PaymentSchedule::with(['contract.customer', 'responsibleUser'])
                    ->where('status', 'overdue')
                    ->orderBy('due_date')
            )
            ->columns([
                Tables\Columns\TextColumn::make('contract.customer.name')
                    ->label('Khách hàng')
                    ->searchable(),
                Tables\Columns\TextColumn::make('contract.contract_number')
                    ->label('Hợp đồng'),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Ngày đến hạn')
                    ->date('d/m/Y'),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Quá hạn (ngày)')
                    ->formatStateUsing(fn ($state) => Carbon::parse($state)->diffInDays(today()) . ' ngày')
                    ->color('danger'),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Số tiền')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 0, ',', '.') . ' ₫')
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('responsibleUser.name')
                    ->label('Phụ trách')
                    ->default('—'),
            ]);
    }
}
