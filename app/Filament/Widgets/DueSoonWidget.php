<?php

namespace App\Filament\Widgets;

use App\Models\PaymentSchedule;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class DueSoonWidget extends BaseWidget
{
    protected static ?int $sort = 3;
    protected static bool $isDiscovered = false;
    protected int|string|array $columnSpan = 'full';
    protected static ?string $heading = 'Đến hạn trong 7 ngày';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                PaymentSchedule::with(['contract.customer', 'responsibleUser'])
                    ->whereIn('status', ['pending', 'invoiced', 'partially_paid'])
                    ->whereBetween('due_date', [today(), today()->addDays(7)])
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
                Tables\Columns\TextColumn::make('amount')
                    ->label('Số tiền')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 0, ',', '.') . ' ₫')
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'pending'       => 'gray',
                        'invoiced'      => 'info',
                        'partially_paid' => 'warning',
                        default         => 'gray',
                    }),
                Tables\Columns\TextColumn::make('responsibleUser.name')
                    ->label('Phụ trách')
                    ->default('—'),
            ]);
    }
}
