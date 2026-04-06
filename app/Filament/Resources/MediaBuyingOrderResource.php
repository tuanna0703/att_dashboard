<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MediaBuyingOrderResource\Pages;
use App\Models\AdNetwork;
use App\Models\Booking;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseItem;
use App\Models\MediaBuyingOrder;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\RawJs;
use Filament\Tables;
use Filament\Tables\Table;

class MediaBuyingOrderResource extends Resource
{
    protected static ?string $model = MediaBuyingOrder::class;
    protected static ?string $navigationIcon  = 'heroicon-o-shopping-cart';
    protected static ?string $navigationGroup = 'Booking';
    protected static ?int    $navigationSort  = 4;
    protected static ?string $modelLabel      = 'Media Buying Order';
    protected static ?string $pluralModelLabel = 'Media Buying Orders';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Thông tin MBO')->schema([
                Forms\Components\TextInput::make('order_no')
                    ->label('Mã MBO')
                    ->disabled()
                    ->dehydrated(false),

                Forms\Components\Select::make('status')
                    ->label('Trạng thái')
                    ->options(MediaBuyingOrder::$statuses)
                    ->disabled()
                    ->dehydrated(false),

                Forms\Components\Select::make('contract_id')
                    ->label('Hợp đồng')
                    ->options(
                        Contract::where('contract_type', 'ads')
                            ->orderByDesc('id')
                            ->with('customer')
                            ->get()
                            ->mapWithKeys(fn ($c) => [$c->id => "[{$c->contract_code}] {$c->customer?->name}"])
                    )
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set, $state) {
                        $contract = Contract::find($state);
                        if ($contract?->booking_id) {
                            $set('booking_id', $contract->booking_id);
                        }
                    }),

                Forms\Components\Select::make('booking_id')
                    ->label('Booking liên quan')
                    ->options(Booking::orderByDesc('id')->pluck('booking_no', 'id'))
                    ->searchable()
                    ->placeholder('Chọn booking (nếu có)'),

                Forms\Components\Select::make('created_by')
                    ->label('AdOps tạo')
                    ->options(User::orderBy('name')->pluck('name', 'id'))
                    ->default(auth()->id())
                    ->required()
                    ->searchable(),

                Forms\Components\Textarea::make('note')
                    ->label('Ghi chú')
                    ->rows(2)
                    ->columnSpanFull(),

                Forms\Components\FileUpload::make('file_path')
                    ->label('Đính kèm')
                    ->directory('media-buying-orders')
                    ->acceptedFileTypes(['application/pdf', 'image/*',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ])
                    ->columnSpanFull(),
            ])->columns(2),

            Forms\Components\Section::make('Chi tiết inventory')
                ->description('Các màn hình / network cần mua.')
                ->schema([
                    Forms\Components\Repeater::make('items')
                        ->relationship('items')
                        ->label('')
                        ->schema([
                            // ── Row 1: Networks + Mô tả ─────────────────────────
                            Forms\Components\Grid::make(2)->schema([
                                Forms\Components\Select::make('targeting')
                                    ->label('Mạng lưới')
                                    ->multiple()
                                    ->options(function ($livewire) {
                                        $bookingId = $livewire->record?->booking_id
                                            ?? data_get($livewire, 'data.booking_id');
                                        if ($bookingId) {
                                            $networkIds = Booking::find($bookingId)
                                                ?->lineItems
                                                ->pluck('targeting')
                                                ->flatten()
                                                ->unique()
                                                ->filter()
                                                ->values();
                                            if ($networkIds && $networkIds->isNotEmpty()) {
                                                return AdNetwork::whereIn('id', $networkIds)->pluck('name', 'id');
                                            }
                                        }
                                        return AdNetwork::where('is_active', true)->pluck('name', 'id');
                                    })
                                    ->afterStateHydrated(function ($component, $state, $record) {
                                        // Already has targeting data — use it
                                        if (! empty($state)) {
                                            return;
                                        }
                                        // Fallback: read from linked booking line item
                                        if ($record?->booking_line_item_id) {
                                            $bli = $record->bookingLineItem;
                                            if ($bli && ! empty($bli->targeting)) {
                                                $component->state(array_map('intval', $bli->targeting));
                                                return;
                                            }
                                        }
                                        // Fallback: single ad_network_id
                                        if ($record?->ad_network_id) {
                                            $component->state([(int) $record->ad_network_id]);
                                        }
                                    })
                                    ->required()
                                    ->searchable(),

                                Forms\Components\TextInput::make('description')
                                    ->label('Mô tả / Màn hình'),
                            ]),

                            // ── Row 2: Từ ngày, Tới ngày, Tổng tiền ────────────
                            Forms\Components\Grid::make(3)->schema([
                                Forms\Components\DatePicker::make('start_date')
                                    ->label('Từ ngày')
                                    ->displayFormat('d/m/Y'),

                                Forms\Components\DatePicker::make('end_date')
                                    ->label('Tới ngày')
                                    ->displayFormat('d/m/Y'),

                                Forms\Components\TextInput::make('total_price')
                                    ->label('Tổng tiền')
                                    ->prefix('₫')
                                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                                    ->dehydrateStateUsing(fn ($state) => (float) str_replace('.', '', (string) ($state ?? 0)))
                                    ->afterStateHydrated(function ($component, $state) {
                                        if ($state !== null && $state !== '') {
                                            $component->state(number_format((float) $state, 0, ',', '.'));
                                        }
                                    }),
                            ]),

                            // ── Row 3: Ghi chú ──────────────────────────────────
                            Forms\Components\TextInput::make('note')
                                ->label('Ghi chú'),
                        ])
                        ->addActionLabel('+ Thêm dòng')
                        ->defaultItems(1)
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(function (array $state): ?string {
                            $targeting = $state['targeting'] ?? [];
                            if (empty($targeting)) return null;
                            $names = AdNetwork::whereIn('id', $targeting)->pluck('name')->implode(', ');
                            $total = (float) str_replace('.', '', (string) ($state['total_price'] ?? 0));
                            return $names . ($total > 0 ? ' — ' . number_format($total, 0, ',', '.') . ' ₫' : '');
                        }),
                ]),
        ]);
    }



    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_no')
                    ->label('Mã MBO')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('contract.contract_code')
                    ->label('Hợp đồng')
                    ->searchable(),

                Tables\Columns\TextColumn::make('booking.booking_no')
                    ->label('Booking')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('AdOps')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Tổng tiền')
                    ->money('VND')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('deptHead.name')
                    ->label('TP duyệt')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('buyer.name')
                    ->label('Buyer')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Trạng thái')
                    ->formatStateUsing(fn ($state) => MediaBuyingOrder::$statuses[$state] ?? $state)
                    ->colors(MediaBuyingOrder::$statusColors),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options(MediaBuyingOrder::$statuses),

                Tables\Filters\SelectFilter::make('contract_id')
                    ->label('Hợp đồng')
                    ->relationship('contract', 'contract_code')
                    ->searchable(),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make()
                        ->visible(fn (MediaBuyingOrder $r) => $r->status === 'draft'),

                    // Gửi trưởng phòng duyệt
                    Tables\Actions\Action::make('submit_dept')
                        ->label('Gửi TP duyệt')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('warning')
                        ->visible(fn (MediaBuyingOrder $r) => $r->status === 'draft')
                        ->requiresConfirmation()
                        ->action(function (MediaBuyingOrder $r) {
                            $r->update(['status' => 'pending_dept']);
                            Notification::make()->title('Đã gửi MBO cho Trưởng phòng duyệt')->success()->send();
                        }),

                    // Trưởng phòng duyệt
                    Tables\Actions\Action::make('dept_approve')
                        ->label('TP Duyệt')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (MediaBuyingOrder $r) => $r->status === 'pending_dept'
                            && auth()->user()->hasPermissionTo('media_buying_orders.approve_dept'))
                        ->requiresConfirmation()
                        ->action(function (MediaBuyingOrder $r) {
                            $r->update([
                                'status'                => 'dept_approved',
                                'dept_head_id'          => auth()->id(),
                                'dept_head_approved_at' => now(),
                            ]);
                            Notification::make()->title('Trưởng phòng đã duyệt — chuyển kết toán')->success()->send();
                        }),

                    // Trưởng phòng từ chối
                    Tables\Actions\Action::make('dept_reject')
                        ->label('TP Từ chối')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (MediaBuyingOrder $r) => $r->status === 'pending_dept'
                            && auth()->user()->hasPermissionTo('media_buying_orders.approve_dept'))
                        ->form([
                            Forms\Components\Textarea::make('rejection_reason')
                                ->label('Lý do từ chối')->required()->rows(3),
                        ])
                        ->action(function (MediaBuyingOrder $r, array $data) {
                            $r->update(['status' => 'rejected', 'rejection_reason' => $data['rejection_reason']]);
                            Notification::make()->title('Đã từ chối MBO')->danger()->send();
                        }),

                    // Gửi kết toán duyệt (sau khi TP duyệt)
                    Tables\Actions\Action::make('submit_finance')
                        ->label('Gửi kết toán')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('info')
                        ->visible(fn (MediaBuyingOrder $r) => $r->status === 'dept_approved')
                        ->requiresConfirmation()
                        ->action(function (MediaBuyingOrder $r) {
                            $r->update(['status' => 'pending_finance']);
                            Notification::make()->title('Đã gửi MBO cho kết toán')->success()->send();
                        }),

                    // Kết toán duyệt
                    Tables\Actions\Action::make('finance_approve')
                        ->label('Kết toán Duyệt')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (MediaBuyingOrder $r) => $r->status === 'pending_finance'
                            && auth()->user()->hasPermissionTo('media_buying_orders.approve_finance'))
                        ->requiresConfirmation()
                        ->action(function (MediaBuyingOrder $r) {
                            $r->update([
                                'status'               => 'finance_approved',
                                'finance_approved_by'  => auth()->id(),
                                'finance_approved_at'  => now(),
                            ]);

                            static::createExpenseFromMBO($r);

                            Notification::make()->title('Kết toán đã duyệt — đã tạo phiếu chi & chuyển Buyer')->success()->send();
                        }),

                    // Kết toán từ chối
                    Tables\Actions\Action::make('finance_reject')
                        ->label('Kết toán Từ chối')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (MediaBuyingOrder $r) => $r->status === 'pending_finance'
                            && auth()->user()->hasPermissionTo('media_buying_orders.approve_finance'))
                        ->form([
                            Forms\Components\Textarea::make('rejection_reason')
                                ->label('Lý do từ chối')->required()->rows(3),
                        ])
                        ->action(function (MediaBuyingOrder $r, array $data) {
                            $r->update(['status' => 'rejected', 'rejection_reason' => $data['rejection_reason']]);
                            Notification::make()->title('Kết toán đã từ chối MBO')->danger()->send();
                        }),

                    // Assign và gửi Buyer
                    Tables\Actions\Action::make('send_to_buyer')
                        ->label('Gửi Buyer')
                        ->icon('heroicon-o-arrow-right-circle')
                        ->color('primary')
                        ->visible(fn (MediaBuyingOrder $r) => $r->status === 'finance_approved')
                        ->form([
                            Forms\Components\Select::make('buyer_id')
                                ->label('Chọn Buyer')
                                ->options(User::orderBy('name')->pluck('name', 'id'))
                                ->required()
                                ->searchable(),
                        ])
                        ->modalHeading('Gửi MBO cho Buyer thực hiện')
                        ->action(function (MediaBuyingOrder $r, array $data) {
                            $r->update([
                                'status'   => 'sent_to_buyer',
                                'buyer_id' => $data['buyer_id'],
                            ]);
                            Notification::make()->title('Đã gửi MBO cho Buyer')->success()->send();
                        }),

                    // Buyer đánh dấu đã thực hiện
                    Tables\Actions\Action::make('mark_executed')
                        ->label('Đã thực hiện mua')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->visible(fn (MediaBuyingOrder $r) => $r->status === 'sent_to_buyer'
                            && auth()->user()->hasRole('media_buyer'))
                        ->requiresConfirmation()
                        ->modalHeading('Xác nhận đã thực hiện mua inventory?')
                        ->action(function (MediaBuyingOrder $r) {
                            $r->update([
                                'status'            => 'executed',
                                'buyer_executed_at' => now(),
                            ]);
                            Notification::make()->title('Buyer đã xác nhận thực hiện mua')->success()->send();
                        }),

                    // Hoàn thành
                    Tables\Actions\Action::make('mark_completed')
                        ->label('Hoàn thành')
                        ->icon('heroicon-o-archive-box')
                        ->color('gray')
                        ->visible(fn (MediaBuyingOrder $r) => $r->status === 'executed')
                        ->requiresConfirmation()
                        ->action(function (MediaBuyingOrder $r) {
                            $r->update(['status' => 'completed']);
                            Notification::make()->title('MBO đã hoàn thành')->success()->send();
                        }),

                    Tables\Actions\DeleteAction::make()
                        ->visible(fn (MediaBuyingOrder $r) => $r->status === 'draft'),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

// ─── Create Expense from approved MBO ─────────────────────────────────────

    public static function createExpenseFromMBO(MediaBuyingOrder $mbo): Expense
    {
        $category = ExpenseCategory::where('code', 'CP-HD-MH')->first();

        $expense = Expense::create([
            'expense_date'        => now()->toDateString(),
            'expense_category_id' => $category?->id,
            'contract_id'         => $mbo->contract_id,
            'total_amount'        => $mbo->total_amount,
            'payment_method'      => 'bank_transfer',
            'recorded_by'         => auth()->id(),
            'status'              => 'approved',
            'approved_by'         => auth()->id(),
            'approved_at'         => now(),
            'note'                => "Tự động tạo từ MBO {$mbo->order_no}",
        ]);

        foreach ($mbo->items()->with('adNetwork')->get() as $item) {
            $networkName = $item->adNetwork?->name ?? '';
            $itemName    = $networkName
                ? $networkName . ' — ' . ($item->description ?? 'Media buying')
                : ($item->description ?? 'Media buying');

            ExpenseItem::create([
                'expense_id' => $expense->id,
                'name'       => $itemName,
                'quantity'   => $item->screen_count * $item->days,
                'unit'       => 'slot',
                'unit_price' => $item->unit_price,
                'amount'     => $item->total_price,
            ]);
        }

        return $expense;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasPermissionTo('media_buying_orders.viewAny');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->hasPermissionTo('media_buying_orders.create');
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasPermissionTo('media_buying_orders.update');
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasPermissionTo('media_buying_orders.delete');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMediaBuyingOrders::route('/'),
            'create' => Pages\CreateMediaBuyingOrder::route('/create'),
            'view'   => Pages\ViewMediaBuyingOrder::route('/{record}'),
            'edit'   => Pages\EditMediaBuyingOrder::route('/{record}/edit'),
        ];
    }
}
