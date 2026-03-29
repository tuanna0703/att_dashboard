<?php

namespace App\Filament\Widgets;

use App\Models\PaymentSchedule;
use App\Support\DepartmentScope;
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
        $query = DepartmentScope::paymentSchedules(
            PaymentSchedule::with(['contract.customer', 'responsibleUser'])
                ->where('status', 'overdue')
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
                    ->date('d/m/Y')
                    ->color('danger'),
                Tables\Columns\TextColumn::make('days_overdue')
                    ->label('Quá hạn (ngày)')
                    ->state(fn ($record) => Carbon::parse($record->due_date)->diffInDays(today()) . ' ngày')
                    ->color('danger'),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Số tiền')
                    ->money('VND')
                    ->alignEnd()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('responsibleUser.name')
                    ->label('Phụ trách')
                    ->placeholder('—'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('view_all_overdue')
                    ->label('Xem tất cả')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('danger')
                    ->url('/admin/payment-schedules?tableFilters[status][value]=overdue'),
            ])
            ->paginated([10, 25]);
    }
}
