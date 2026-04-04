<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookingResource\Pages;
use App\Filament\Resources\BookingResource\RelationManagers;
use App\Filament\Resources\PlanResource;
use App\Filament\Resources\Shared\ActivityLogRelationManager;
use App\Models\Booking;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\RawJs;
use Filament\Tables;
use Filament\Tables\Table;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;
    protected static ?string $navigationIcon  = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'Booking';
    protected static ?int    $navigationSort  = 3;
    protected static ?string $modelLabel      = 'Booking';
    protected static ?string $pluralModelLabel = 'Bookings';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Thông tin Booking')->schema([
                Forms\Components\TextInput::make('booking_no')
                    ->label('Mã Booking')
                    ->disabled()
                    ->dehydrated(false),

                Forms\Components\Select::make('status')
                    ->label('Trạng thái')
                    ->options(Booking::$statuses)
                    ->disabled()
                    ->dehydrated(false),

                Forms\Components\TextInput::make('campaign_name')
                    ->label('Tên Campaign')
                    ->required()
                    ->maxLength(200)
                    ->columnSpan(2),

                Forms\Components\DatePicker::make('start_date')
                    ->label('Ngày bắt đầu')
                    ->required()
                    ->displayFormat('d/m/Y'),

                Forms\Components\DatePicker::make('end_date')
                    ->label('Ngày kết thúc')
                    ->required()
                    ->displayFormat('d/m/Y'),

                Forms\Components\TextInput::make('total_budget')
                    ->label('Ngân sách (VND)')
                    ->prefix('₫')
                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                    ->dehydrateStateUsing(fn ($state) => $state ? (float) str_replace('.', '', (string) $state) : null)
                    ->afterStateHydrated(function ($component, $state) {
                        if ($state !== null) {
                            $component->state(number_format((float) $state, 0, ',', '.'));
                        }
                    }),

                Forms\Components\Textarea::make('note')
                    ->label('Ghi chú')
                    ->rows(2)
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('campaign_name')
                    ->label('Booking')
                    ->html()
                    ->formatStateUsing(function (Booking $record) {
                        return '<div class="font-semibold text-gray-950 dark:text-white">' . e($record->campaign_name) . '</div>'
                            . '<div class="text-xs text-gray-400 mt-0.5">' . e($record->booking_no) . '</div>';
                    })
                    ->searchable(query: fn ($query, string $search) =>
                        $query->where('booking_no', 'like', "%{$search}%")
                            ->orWhere('campaign_name', 'like', "%{$search}%")
                    )
                    ->url(fn (Booking $record) => static::getUrl('view', ['record' => $record])),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Khách hàng')
                    ->searchable(),

                Tables\Columns\TextColumn::make('sale.name')
                    ->label('Sale')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('adops.name')
                    ->label('AdOps')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('total_budget')
                    ->label('Ngân sách')
                    ->money(fn (Booking $record) => $record->currency ?? 'VND')
                    ->alignEnd()
                    ->weight('bold')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Booking::$statuses[$state] ?? $state)
                    ->color(fn ($state) => Booking::$statusColors[$state] ?? 'gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options(Booking::$statuses),

                Tables\Filters\SelectFilter::make('customer_id')
                    ->label('Khách hàng')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('no_contract')
                    ->label('Chưa có hợp đồng')
                    ->query(fn ($query) => $query->whereNull('contract_id')),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make()
                        ->visible(fn (Booking $record) => ! in_array($record->status, ['closed', 'cancelled'])),

                    // Tạo hợp đồng từ Booking
                    Tables\Actions\Action::make('create_contract')
                        ->label('Tạo hợp đồng')
                        ->icon('heroicon-o-document-plus')
                        ->color('primary')
                        ->visible(fn (Booking $record) => is_null($record->contract_id))
                        ->url(fn (Booking $record) => ContractResource::getUrl('create', [
                            'booking_id'    => $record->id,
                            'customer_id'   => $record->customer_id,
                            'campaign_name' => $record->campaign_name,
                            'start_date'    => $record->start_date?->format('Y-m-d'),
                            'end_date'      => $record->end_date?->format('Y-m-d'),
                            'total_value'   => $record->total_budget,
                        ])),

                    // Cập nhật trạng thái chiến dịch
                    Tables\Actions\Action::make('mark_active')
                        ->label('Bắt đầu chạy')
                        ->icon('heroicon-o-play')
                        ->color('success')
                        ->visible(fn (Booking $record) => $record->status === 'contract_signed')
                        ->requiresConfirmation()
                        ->action(function (Booking $record) {
                            $record->update(['status' => 'campaign_active']);
                            Notification::make()->title('Campaign đang chạy')->success()->send();
                        }),

                    Tables\Actions\Action::make('mark_completed')
                        ->label('Đánh dấu đã chạy xong')
                        ->icon('heroicon-o-flag')
                        ->color('info')
                        ->visible(fn (Booking $record) => $record->status === 'campaign_active')
                        ->requiresConfirmation()
                        ->action(function (Booking $record) {
                            $record->update(['status' => 'campaign_completed']);
                            Notification::make()->title('Campaign đã kết thúc — chờ nghiệm thu')->success()->send();
                        }),

                    Tables\Actions\Action::make('cancel')
                        ->label('Huỷ Booking')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (Booking $record) => ! in_array($record->status, ['closed', 'cancelled', 'acceptance_done']))
                        ->requiresConfirmation()
                        ->modalHeading('Huỷ Booking này?')
                        ->action(function (Booking $record) {
                            $record->update(['status' => 'cancelled']);
                            Notification::make()->title('Đã huỷ Booking')->danger()->send();
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

public static function getRelationManagers(): array
    {
        return [
            RelationManagers\LineItemsRelationManager::class,
            ActivityLogRelationManager::class,
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasPermissionTo('bookings.viewAny');
    }

    public static function canCreate(): bool
    {
        return false; // Booking chỉ tạo từ Brief
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasPermissionTo('bookings.update');
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListBookings::route('/'),
            'view'   => Pages\ViewBooking::route('/{record}'),
            'edit'   => Pages\EditBooking::route('/{record}/edit'),
        ];
    }
}
