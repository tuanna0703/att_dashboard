<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseResource\Pages;
use App\Models\CompanyBank;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Models\Vendor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\RawJs;
use Filament\Tables;
use Filament\Tables\Table;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;
    protected static ?string $navigationIcon  = 'heroicon-o-credit-card';
    protected static ?string $navigationGroup = 'Chi phí';
    protected static ?int    $navigationSort  = 1;
    protected static ?string $modelLabel      = 'Phiếu chi';
    protected static ?string $pluralModelLabel = 'Phiếu chi';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Thông tin phiếu chi')->schema([
                Forms\Components\TextInput::make('expense_no')
                    ->label('Mã phiếu chi')
                    ->placeholder('Tự động tạo')
                    ->disabled()
                    ->dehydrated(false),

                Forms\Components\DatePicker::make('expense_date')
                    ->label('Ngày chi')
                    ->required()
                    ->default(now())
                    ->displayFormat('d/m/Y'),

                Forms\Components\Select::make('expense_category_id')
                    ->label('Danh mục chi phí')
                    ->options(function () {
                        return ExpenseCategory::with('parent')
                            ->where('is_active', true)
                            ->get()
                            ->mapWithKeys(fn ($cat) => [
                                $cat->id => $cat->parent
                                    ? $cat->parent->name . ' / ' . $cat->name
                                    : $cat->name,
                            ]);
                    })
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('contract_id', null)),

                Forms\Components\Select::make('vendor_id')
                    ->label('Nhà cung cấp')
                    ->options(Vendor::where('is_active', true)->pluck('name', 'id'))
                    ->searchable()
                    ->placeholder('Chọn nhà cung cấp...')
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')->label('Tên NCC')->required(),
                        Forms\Components\TextInput::make('phone')->label('Điện thoại'),
                        Forms\Components\TextInput::make('tax_code')->label('Mã số thuế'),
                    ])
                    ->createOptionUsing(function (array $data) {
                        return Vendor::create(array_merge($data, ['is_active' => true]))->id;
                    }),

                // Khách hàng — chỉ để lọc hợp đồng, không lưu DB
                Forms\Components\Select::make('_customer_id')
                    ->label('Khách hàng (lọc HĐ)')
                    ->options(Customer::orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->placeholder('Chọn để lọc hợp đồng...')
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('contract_id', null))
                    ->dehydrated(false),

                Forms\Components\Select::make('contract_id')
                    ->label('Hợp đồng liên quan')
                    ->options(function (Get $get) {
                        $customerId = $get('_customer_id');
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
                    ->placeholder('Không liên quan hợp đồng'),

                Forms\Components\Select::make('payment_method')
                    ->label('Hình thức chi')
                    ->options([
                        'bank_transfer' => 'Chuyển khoản',
                        'cash'          => 'Tiền mặt',
                        'cheque'        => 'Séc',
                    ])
                    ->default('bank_transfer')
                    ->required(),

                Forms\Components\Select::make('company_bank_id')
                    ->label('Tài khoản chi')
                    ->options(CompanyBank::all()->mapWithKeys(fn ($b) => [
                        $b->id => "{$b->bank_name} — {$b->account_number} ({$b->account_name})",
                    ]))
                    ->searchable()
                    ->placeholder('Chọn tài khoản...')
                    ->default(fn () => CompanyBank::where('is_default', true)->value('id')),

                Forms\Components\TextInput::make('reference_no')
                    ->label('Số chứng từ / Mã GD'),

                Forms\Components\Select::make('recorded_by')
                    ->label('Người lập phiếu')
                    ->options(User::pluck('name', 'id'))
                    ->default(auth()->id())
                    ->searchable(),

                Forms\Components\Textarea::make('note')
                    ->label('Ghi chú')
                    ->rows(2)
                    ->columnSpanFull(),

                Forms\Components\FileUpload::make('file_path')
                    ->label('Đính kèm chứng từ')
                    ->directory('expenses')
                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                    ->columnSpanFull(),
            ])->columns(2),

            Forms\Components\Section::make('Khoản mục chi tiết')
                ->description('Nhập chi tiết từng khoản mục chi phí.')
                ->schema([
                    Forms\Components\Repeater::make('items')
                        ->relationship('items')
                        ->label('')
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->label('Tên khoản mục')
                                ->required()
                                ->placeholder('VD: Mua nhân công thi công')
                                ->columnSpan(3),

                            Forms\Components\TextInput::make('quantity')
                                ->label('Số lượng')
                                ->numeric()
                                ->default(1)
                                ->live(debounce: 500)
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    $qty   = (float) ($get('quantity') ?? 1);
                                    $price = (float) str_replace('.', '', (string) ($get('unit_price') ?? 0));
                                    $set('amount', number_format($qty * $price, 0, ',', '.'));
                                }),

                            Forms\Components\TextInput::make('unit')
                                ->label('ĐVT')
                                ->placeholder('cái, kg, lần...'),

                            Forms\Components\TextInput::make('unit_price')
                                ->label('Đơn giá')
                                ->prefix('₫')
                                ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                                ->dehydrateStateUsing(fn ($state) => (float) str_replace('.', '', (string) ($state ?? 0)))
                                ->afterStateHydrated(function ($component, $state) {
                                    if ($state !== null && $state !== '') {
                                        $component->state(number_format((float) $state, 0, ',', '.'));
                                    }
                                })
                                ->live(debounce: 500)
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    $qty   = (float) ($get('quantity') ?? 1);
                                    $price = (float) str_replace('.', '', (string) ($get('unit_price') ?? 0));
                                    $set('amount', number_format($qty * $price, 0, ',', '.'));
                                }),

                            Forms\Components\TextInput::make('amount')
                                ->label('Thành tiền')
                                ->prefix('₫')
                                ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                                ->dehydrateStateUsing(fn ($state) => (float) str_replace('.', '', (string) ($state ?? 0)))
                                ->afterStateHydrated(function ($component, $state) {
                                    if ($state !== null && $state !== '') {
                                        $component->state(number_format((float) $state, 0, ',', '.'));
                                    }
                                })
                                ->required()
                                ->rule(fn () => function ($attribute, $value, $fail) {
                                    $numeric = (float) str_replace('.', '', (string) ($value ?? 0));
                                    if ($numeric < 1) {
                                        $fail('Thành tiền phải lớn hơn 0.');
                                    }
                                }),

                            Forms\Components\TextInput::make('note')
                                ->label('Ghi chú')
                                ->placeholder('Ghi chú thêm...')
                                ->columnSpan(2),
                        ])
                        ->columns(6)
                        ->addActionLabel('+ Thêm khoản mục')
                        ->defaultItems(1)
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(function (array $state): ?string {
                            $name   = $state['name'] ?? '';
                            $amount = (float) str_replace('.', '', (string) ($state['amount'] ?? 0));
                            if (! $name) {
                                return null;
                            }
                            return $name . ($amount > 0 ? ' — ' . number_format($amount, 0, ',', '.') . ' ₫' : '');
                        }),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('expense_no')
                    ->label('Mã phiếu')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('expense_date')
                    ->label('Ngày chi')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Danh mục')
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('contract.contract_code')
                    ->label('Hợp đồng')
                    ->placeholder('—')
                    ->searchable(),

                Tables\Columns\TextColumn::make('vendor.name')
                    ->label('Nhà cung cấp')
                    ->placeholder('—')
                    ->searchable(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Tổng tiền')
                    ->money('VND')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\BadgeColumn::make('payment_method')
                    ->label('Hình thức')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'bank_transfer' => 'CK',
                        'cash'          => 'Tiền mặt',
                        'cheque'        => 'Séc',
                        default         => $state,
                    })
                    ->colors([
                        'primary' => 'bank_transfer',
                        'success' => 'cash',
                        'warning' => 'cheque',
                    ]),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Trạng thái')
                    ->formatStateUsing(fn ($state) => Expense::$statuses[$state] ?? $state)
                    ->colors(Expense::$statusColors),

                Tables\Columns\TextColumn::make('recordedBy.name')
                    ->label('Người lập')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('approvedBy.name')
                    ->label('Người duyệt')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options(Expense::$statuses),

                Tables\Filters\SelectFilter::make('expense_category_id')
                    ->label('Danh mục')
                    ->options(fn () => ExpenseCategory::where('is_active', true)->pluck('name', 'id'))
                    ->searchable(),

                Tables\Filters\SelectFilter::make('contract_id')
                    ->label('Hợp đồng')
                    ->relationship('contract', 'contract_code')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('vendor_id')
                    ->label('Nhà cung cấp')
                    ->relationship('vendor', 'name')
                    ->searchable(),

                Tables\Filters\Filter::make('expense_date')
                    ->label('Tháng này')
                    ->query(fn ($query) => $query->whereBetween('expense_date', [now()->startOfMonth(), now()->endOfMonth()])),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make()
                        ->visible(fn (Expense $record) => in_array($record->status, ['draft', 'rejected'])),

                    // Submit for approval
                    Tables\Actions\Action::make('submit')
                        ->label('Gửi duyệt')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('warning')
                        ->visible(fn (Expense $record) => $record->status === 'draft')
                        ->requiresConfirmation()
                        ->modalHeading('Gửi phiếu chi để duyệt?')
                        ->action(function (Expense $record) {
                            $record->update(['status' => 'pending']);
                            Notification::make()->title('Đã gửi phiếu chi để duyệt')->success()->send();
                        }),

                    // Approve
                    Tables\Actions\Action::make('approve')
                        ->label('Duyệt')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (Expense $record) => $record->status === 'pending' && auth()->user()->hasPermissionTo('expenses.approve'))
                        ->requiresConfirmation()
                        ->modalHeading('Duyệt phiếu chi?')
                        ->action(function (Expense $record) {
                            $record->update([
                                'status'      => 'approved',
                                'approved_by' => auth()->id(),
                                'approved_at' => now(),
                            ]);
                            Notification::make()->title('Đã duyệt phiếu chi')->success()->send();
                        }),

                    // Reject
                    Tables\Actions\Action::make('reject')
                        ->label('Từ chối')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (Expense $record) => $record->status === 'pending' && auth()->user()->hasPermissionTo('expenses.approve'))
                        ->form([
                            Forms\Components\Textarea::make('rejection_reason')
                                ->label('Lý do từ chối')
                                ->required()
                                ->rows(3),
                        ])
                        ->modalHeading('Từ chối phiếu chi')
                        ->action(function (Expense $record, array $data) {
                            $record->update([
                                'status'           => 'rejected',
                                'rejection_reason' => $data['rejection_reason'],
                            ]);
                            Notification::make()->title('Đã từ chối phiếu chi')->danger()->send();
                        }),

                    // Mark paid
                    Tables\Actions\Action::make('mark_paid')
                        ->label('Đánh dấu đã chi')
                        ->icon('heroicon-o-banknotes')
                        ->color('primary')
                        ->visible(fn (Expense $record) => $record->status === 'approved')
                        ->requiresConfirmation()
                        ->modalHeading('Xác nhận đã thực hiện chi?')
                        ->action(function (Expense $record) {
                            $record->update(['status' => 'paid']);
                            Notification::make()->title('Đã cập nhật trạng thái Đã thanh toán')->success()->send();
                        }),

                    // Reset to draft
                    Tables\Actions\Action::make('reset_draft')
                        ->label('Chuyển về Nháp')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('gray')
                        ->visible(fn (Expense $record) => $record->status === 'rejected')
                        ->requiresConfirmation()
                        ->action(function (Expense $record) {
                            $record->update([
                                'status'           => 'draft',
                                'rejection_reason' => null,
                            ]);
                            Notification::make()->title('Đã chuyển về trạng thái Nháp')->success()->send();
                        }),

                    Tables\Actions\DeleteAction::make()
                        ->visible(fn (Expense $record) => $record->status === 'draft'),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('expense_date', 'desc');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasPermissionTo('expenses.viewAny');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->hasPermissionTo('expenses.create');
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasPermissionTo('expenses.update');
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasPermissionTo('expenses.delete');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'view'   => Pages\ViewExpense::route('/{record}'),
            'edit'   => Pages\EditExpense::route('/{record}/edit'),
        ];
    }
}
