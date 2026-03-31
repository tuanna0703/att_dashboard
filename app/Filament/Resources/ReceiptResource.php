<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReceiptResource\Pages;
use App\Filament\Resources\ReceiptResource\RelationManagers;
use App\Models\CompanyBank;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\PaymentSchedule;
use App\Models\Receipt;
use App\Models\User;
use App\Support\DepartmentScope;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReceiptResource extends Resource
{
    protected static ?string $model = Receipt::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Công nợ';
    protected static ?int $navigationSort = 3;
    protected static ?string $modelLabel = 'Phiếu thu';
    protected static ?string $pluralModelLabel = 'Phiếu thu';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Thông tin phiếu thu')->schema([
                // Khách hàng — chỉ để lọc, không lưu DB
                Forms\Components\Select::make('customer_id')
                    ->label('Khách hàng')
                    ->options(Customer::orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->placeholder('Chọn khách hàng...')
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('contract_id', null))
                    ->dehydrated(false),

                Forms\Components\Select::make('contract_id')
                    ->label('Hợp đồng')
                    ->options(function (Get $get) {
                        $customerId = $get('customer_id');
                        if (! $customerId) {
                            return Contract::orderByDesc('id')
                                ->with('customer')
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn ($c) => [$c->id => "[{$c->contract_code}] {$c->customer->name}"]);
                        }
                        return Contract::where('customer_id', $customerId)
                            ->orderByDesc('id')
                            ->pluck('contract_code', 'id');
                    })
                    ->searchable()
                    ->placeholder('Chọn hợp đồng...')
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('allocations', []))
                    ->required(),

                Forms\Components\DatePicker::make('receipt_date')
                    ->label('Ngày thu')
                    ->required()
                    ->default(now()),

                Forms\Components\TextInput::make('amount')
                    ->label('Số tiền thu')
                    ->numeric()
                    ->prefix('VND')
                    ->required()
                    ->live(onBlur: true),

                Forms\Components\Select::make('payment_method')
                    ->label('Hình thức thanh toán')
                    ->options([
                        'bank_transfer' => 'Chuyển khoản',
                        'cash'          => 'Tiền mặt',
                        'cheque'        => 'Séc',
                    ])
                    ->default('bank_transfer')
                    ->required(),

                Forms\Components\Select::make('company_bank_id')
                    ->label('Tài khoản nhận tiền')
                    ->options(CompanyBank::all()->mapWithKeys(fn ($b) => [
                        $b->id => "{$b->bank_name} — {$b->account_number} ({$b->account_name})",
                    ]))
                    ->searchable()
                    ->placeholder('Chọn tài khoản ngân hàng...')
                    ->default(fn () => CompanyBank::where('is_default', true)->value('id')),

                Forms\Components\TextInput::make('reference_no')
                    ->label('Số tham chiếu / Mã GD'),

                Forms\Components\Select::make('recorded_by')
                    ->label('Người ghi nhận')
                    ->options(User::pluck('name', 'id'))
                    ->default(auth()->id())
                    ->searchable(),

                Forms\Components\Textarea::make('note')
                    ->label('Ghi chú')
                    ->rows(2)
                    ->columnSpanFull(),
            ])->columns(2),

            Forms\Components\Section::make('Phân bổ vào lịch thanh toán')
                ->description('Chọn các đợt thanh toán để phân bổ số tiền thu vào.')
                ->schema([
                    Forms\Components\Repeater::make('allocations')
                        ->relationship('allocations')
                        ->label('')
                        ->schema([
                            Forms\Components\Select::make('payment_schedule_id')
                                ->label('Đợt thanh toán')
                                ->options(function (Get $get) {
                                    $contractId = $get('../../contract_id');
                                    if (! $contractId) {
                                        return [];
                                    }
                                    return PaymentSchedule::where('contract_id', $contractId)
                                        ->whereNotIn('status', ['paid', 'cancelled'])
                                        ->orderBy('installment_no')
                                        ->get()
                                        ->mapWithKeys(fn ($ps) => [
                                            $ps->id => "Đợt {$ps->installment_no} — " .
                                                number_format($ps->amount, 0, ',', '.') . ' VND' .
                                                ' — HH: ' . $ps->due_date->format('d/m/Y') .
                                                ' [' . match ($ps->status) {
                                                    'pending'        => 'Chờ',
                                                    'invoiced'       => 'Đã HĐ',
                                                    'partially_paid' => 'Thu 1 phần',
                                                    'overdue'        => 'Quá hạn',
                                                    default          => $ps->status,
                                                } . ']',
                                        ]);
                                })
                                ->searchable()
                                ->required()
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                ->live()
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('allocated_amount')
                                ->label('Số tiền phân bổ')
                                ->numeric()
                                ->prefix('VND')
                                ->required()
                                ->minValue(1),
                        ])
                        ->columns(3)
                        ->addActionLabel('+ Thêm đợt thanh toán')
                        ->itemLabel(function (array $state): ?string {
                            if (empty($state['payment_schedule_id'])) {
                                return null;
                            }
                            $ps = PaymentSchedule::find($state['payment_schedule_id']);
                            return $ps
                                ? "Đợt {$ps->installment_no} — " . number_format($state['allocated_amount'] ?? 0, 0, ',', '.') . ' VND'
                                : null;
                        })
                        ->collapsible(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('receipt_date')
                    ->label('Ngày thu')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('contract.customer.name')
                    ->label('Khách hàng')
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('contract.contract_code')
                    ->label('Hợp đồng')
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Số tiền')
                    ->money('VND')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\BadgeColumn::make('payment_method')
                    ->label('Hình thức')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'bank_transfer' => 'Chuyển khoản',
                        'cash'          => 'Tiền mặt',
                        'cheque'        => 'Séc',
                        default         => $state,
                    })
                    ->colors([
                        'primary' => 'bank_transfer',
                        'success' => 'cash',
                        'warning' => 'cheque',
                    ]),

                Tables\Columns\TextColumn::make('companyBank.bank_name')
                    ->label('TK nhận')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('reference_no')
                    ->label('Số TK/Mã GD')
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('allocations_count')
                    ->label('Đợt phân bổ')
                    ->counts('allocations')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('recordedBy.name')
                    ->label('Người ghi')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tạo lúc')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('Hình thức')
                    ->options([
                        'bank_transfer' => 'Chuyển khoản',
                        'cash'          => 'Tiền mặt',
                        'cheque'        => 'Séc',
                    ]),

                Tables\Filters\SelectFilter::make('contract_id')
                    ->label('Hợp đồng')
                    ->relationship('contract', 'contract_code')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('receipt_date')
                    ->label('Tháng này')
                    ->query(fn ($query) => $query->whereBetween('receipt_date', [now()->startOfMonth(), now()->endOfMonth()])),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->defaultSort('receipt_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getEloquentQuery(): Builder
    {
        return DepartmentScope::receipts(parent::getEloquentQuery(), auth()->user());
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasPermissionTo('receipts.viewAny');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->hasPermissionTo('receipts.create');
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasPermissionTo('receipts.update');
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasPermissionTo('receipts.delete');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListReceipts::route('/'),
            'create' => Pages\CreateReceipt::route('/create'),
            'view'   => Pages\ViewReceipt::route('/{record}'),
            'edit'   => Pages\EditReceipt::route('/{record}/edit'),
        ];
    }
}
