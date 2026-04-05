<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MediaBuyingOrderResource\Pages;
use App\Models\AdNetwork;
use App\Models\Booking;
use App\Models\Contract;
use App\Models\MediaBuyingOrder;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
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
                            Forms\Components\Select::make('ad_network_id')
                                ->label('Mạng lưới')
                                ->options(AdNetwork::where('is_active', true)->pluck('name', 'id'))
                                ->required()
                                ->searchable()
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('description')
                                ->label('Mô tả vị trí / màn hình')
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('screen_count')
                                ->label('Số màn hình')
                                ->numeric()
                                ->default(1)
                                ->minValue(1)
                                ->live(debounce: 400)
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    $set('total_price', self::calcTotal($get));
                                }),

                            Forms\Components\TextInput::make('days')
                                ->label('Số ngày')
                                ->numeric()
                                ->default(1)
                                ->minValue(1)
                                ->live(debounce: 400)
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    $set('total_price', self::calcTotal($get));
                                }),

                            Forms\Components\TextInput::make('unit_price')
                                ->label('Đơn giá / ngày')
                                ->prefix('₫')
                                ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                                ->dehydrateStateUsing(fn ($s) => (float) str_replace('.', '', (string) ($s ?? 0)))
                                ->afterStateHydrated(function ($component, $state) {
                                    if ($state !== null && $state !== '') {
                                        $component->state(number_format((float) $state, 0, ',', '.'));
                                    }
                                })
                                ->live(debounce: 400)
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    $set('total_price', self::calcTotal($get));
                                }),

                            Forms\Components\TextInput::make('total_price')
                                ->label('Thành tiền')
                                ->prefix('₫')
                                ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                                ->dehydrateStateUsing(fn ($s) => (float) str_replace('.', '', (string) ($s ?? 0)))
                                ->afterStateHydrated(function ($component, $state) {
                                    if ($state !== null && $state !== '') {
                                        $component->state(number_format((float) $state, 0, ',', '.'));
                                    }
                                })
                                ->disabled(),

                            Forms\Components\TextInput::make('note')
                                ->label('Ghi chú')
                                ->columnSpan(2),
                        ])
                        ->columns(9)
                        ->addActionLabel('+ Thêm dòng')
                        ->defaultItems(1)
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(function (array $state): ?string {
                            $networkId = $state['ad_network_id'] ?? null;
                            if (! $networkId) return null;
                            $network = AdNetwork::find($networkId);
                            $total   = (float) str_replace('.', '', (string) ($state['total_price'] ?? 0));
                            return $network?->name . ($total > 0 ? ' — ' . number_format($total, 0, ',', '.') . ' ₫' : '');
                        }),
                ]),
        ]);
    }

    private static function calcTotal(Get $get): string
    {
        $screens    = (int) ($get('screen_count') ?? 1);
        $days       = (int) ($get('days') ?? 1);
        $unitPrice  = (float) str_replace('.', '', (string) ($get('unit_price') ?? 0));
        return number_format($screens * $days * $unitPrice, 0, ',', '.');
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
                            && auth()->user()->hasAnyRole(['coo', 'vice_ceo', 'ceo']))
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
                            && auth()->user()->hasAnyRole(['coo', 'vice_ceo', 'ceo']))
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
                            && auth()->user()->hasAnyRole(['finance_manager', 'ceo']))
                        ->requiresConfirmation()
                        ->action(function (MediaBuyingOrder $r) {
                            $r->update([
                                'status'               => 'finance_approved',
                                'finance_approved_by'  => auth()->id(),
                                'finance_approved_at'  => now(),
                            ]);
                            Notification::make()->title('Kết toán đã duyệt — chuyển Buyer')->success()->send();
                        }),

                    // Kết toán từ chối
                    Tables\Actions\Action::make('finance_reject')
                        ->label('Kết toán Từ chối')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (MediaBuyingOrder $r) => $r->status === 'pending_finance'
                            && auth()->user()->hasAnyRole(['finance_manager', 'ceo']))
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
