<?php

namespace App\Filament\Widgets;

use App\Models\PaymentSchedule;
use App\Support\DepartmentScope;
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
        $query = DepartmentScope::paymentSchedules(
            PaymentSchedule::with(['contract.customer', 'responsibleUser'])
                ->whereIn('status', ['pending', 'invoiced', 'partially_paid'])
                ->whereBetween('due_date', [today(), today()->addDays(7)])
                ->orderBy('due_date'),
            auth()->user()
        );

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('contract.customer.name')
                    ->label('Khách hàng')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('contract.contract_code')
                    ->label('Hợp đồng')
                    ->searchable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Ngày đến hạn')
                    ->date('d/m/Y'),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Số tiền')
                    ->money('VND')
                    ->alignEnd()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'pending'        => 'Chờ',
                        'invoiced'       => 'Đã HĐ',
                        'partially_paid' => 'Thu 1 phần',
                        default          => $state,
                    })
                    ->color(fn ($state) => match ($state) {
                        'pending'        => 'gray',
                        'invoiced'       => 'primary',
                        'partially_paid' => 'warning',
                        default          => 'gray',
                    }),
                Tables\Columns\TextColumn::make('responsibleUser.name')
                    ->label('Phụ trách')
                    ->placeholder('—'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('view_all_due_soon')
                    ->label('Xem tất cả')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('warning')
                    ->url('/admin/payment-schedules?tableFilters[due_this_month][isActive]=true'),
            ])
            ->paginated([10, 25]);
    }
}
