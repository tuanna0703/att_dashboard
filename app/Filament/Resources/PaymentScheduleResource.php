<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentScheduleResource\Pages;
use App\Models\PaymentSchedule;
use App\Models\User;
use App\Support\DepartmentScope;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentScheduleResource extends Resource
{
    protected static ?string $model = PaymentSchedule::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'Công nợ';
    protected static ?int $navigationSort = 1;
    protected static ?string $modelLabel = 'Lịch thanh toán';
    protected static ?string $pluralModelLabel = 'Lịch thanh toán';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\Select::make('contract_id')
                    ->label('Hợp đồng')
                    ->relationship('contract', 'contract_code')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->columnSpan(2),
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
                Forms\Components\DatePicker::make('invoice_issued_date')
                    ->label('Ngày xuất HĐ thực tế'),
                Forms\Components\Select::make('responsible_user_id')
                    ->label('Người phụ trách')
                    ->options(User::pluck('name', 'id'))
                    ->searchable(),
                Forms\Components\Select::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'pending'        => 'Chờ xử lý',
                        'invoiced'       => 'Đã xuất HĐ',
                        'partially_paid' => 'Thu một phần',
                        'paid'           => 'Đã thu',
                        'overdue'        => 'Quá hạn',
                    ])
                    ->default('pending')
                    ->required(),
                Forms\Components\Textarea::make('note')->label('Ghi chú')->columnSpan(2),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('contract.contract_code')
                    ->label('Hợp đồng')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('contract.customer.name')
                    ->label('Khách hàng')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('installment_no')
                    ->label('Đợt')
                    ->alignCenter(),
                Tables\Columns\BadgeColumn::make('schedule_type')
                    ->label('Loại')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'advance'      => 'Tạm ứng',
                        'milestone'    => 'Milestone',
                        'acceptance'   => 'Nghiệm thu',
                        'subscription' => 'Thuê bao',
                        default        => $state,
                    }),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Số tiền')
                    ->money('VND')
                    ->sortable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Hạn TT')
                    ->date('d/m/Y')
                    ->sortable()
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
                Tables\Columns\TextColumn::make('responsibleUser.name')
                    ->label('Phụ trách')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'pending'        => 'Chờ xử lý',
                        'invoiced'       => 'Đã xuất HĐ',
                        'partially_paid' => 'Thu một phần',
                        'paid'           => 'Đã thu',
                        'overdue'        => 'Quá hạn',
                    ]),
                Tables\Filters\SelectFilter::make('schedule_type')
                    ->label('Loại đợt')
                    ->options([
                        'advance'      => 'Tạm ứng',
                        'milestone'    => 'Milestone',
                        'acceptance'   => 'Nghiệm thu',
                        'subscription' => 'Thuê bao',
                    ]),
                Tables\Filters\Filter::make('overdue')
                    ->label('Chỉ quá hạn')
                    ->query(fn ($query) => $query->where('status', 'overdue')),
                Tables\Filters\Filter::make('due_this_month')
                    ->label('Đến hạn tháng này')
                    ->query(fn ($query) => $query->whereBetween('due_date', [now()->startOfMonth(), now()->endOfMonth()])),
                Tables\Filters\SelectFilter::make('contract')
                    ->label('Hợp đồng')
                    ->relationship('contract', 'contract_code')
                    ->searchable()
                    ->preload(),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                ]),
            ])
            ->defaultSort('due_date');
    }

    public static function getEloquentQuery(): Builder
    {
        return DepartmentScope::paymentSchedules(parent::getEloquentQuery(), auth()->user());
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasPermissionTo('payment_schedules.viewAny');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->hasPermissionTo('payment_schedules.create');
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasPermissionTo('payment_schedules.update');
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasPermissionTo('payment_schedules.delete');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPaymentSchedules::route('/'),
            'create' => Pages\CreatePaymentSchedule::route('/create'),
            'view'   => Pages\ViewPaymentSchedule::route('/{record}'),
            'edit'   => Pages\EditPaymentSchedule::route('/{record}/edit'),
        ];
    }
}
