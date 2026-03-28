<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopDebtorsWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    protected static bool $isDiscovered = false;
    protected int|string|array $columnSpan = 'full';
    protected static ?string $heading = 'Top 10 Khách hàng nợ nhiều nhất';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Customer::query()
                    ->selectRaw('customers.id, customers.name, customers.tax_code, SUM(payment_schedules.amount) as outstanding')
                    ->join('contracts', 'customers.id', '=', 'contracts.customer_id')
                    ->join('payment_schedules', 'contracts.id', '=', 'payment_schedules.contract_id')
                    ->whereIn('payment_schedules.status', ['pending', 'invoiced', 'partially_paid', 'overdue'])
                    ->whereNull('payment_schedules.deleted_at')
                    ->whereNull('contracts.deleted_at')
                    ->groupBy('customers.id', 'customers.name', 'customers.tax_code')
                    ->orderByDesc('outstanding')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Khách hàng')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tax_code')
                    ->label('Mã số thuế'),
                Tables\Columns\TextColumn::make('outstanding')
                    ->label('Tổng nợ')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 0, ',', '.') . ' ₫')
                    ->sortable()
                    ->alignEnd(),
            ])
            ->paginated(false);
    }
}
