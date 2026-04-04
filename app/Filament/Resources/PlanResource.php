<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BriefResource;
use App\Filament\Resources\PlanResource\Pages;
use App\Filament\Resources\PlanResource\RelationManagers;
use App\Filament\Resources\Shared\ActivityLogRelationManager;
use App\Models\AdNetwork;
use App\Models\Booking;
use App\Models\BookingLineItem;
use App\Models\Plan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;
    protected static ?string $navigationIcon  = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Booking';
    protected static ?int    $navigationSort  = 8;
    protected static ?string $modelLabel       = 'Plan';
    protected static ?string $pluralModelLabel = 'Plans';

    // Plans are created from Brief context — hide "New Plan" button globally
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasPermissionTo('plans.viewAny');
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasPermissionTo('plans.update')
            && in_array($record->status, ['draft', 're_plan']);
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasPermissionTo('plans.delete');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Thông tin kế hoạch')->schema([
                Forms\Components\TextInput::make('plan_no')
                    ->label('Mã Plan')
                    ->disabled(),

                Forms\Components\TextInput::make('version')
                    ->label('Phiên bản')
                    ->prefix('v')
                    ->disabled(),

                Forms\Components\TextInput::make('budget')
                    ->label('Ngân sách (VND)')
                    ->prefix('₫')
                    ->disabled()
                    ->helperText('Tự động tính từ line items'),

                Forms\Components\TextInput::make('screen_count')
                    ->label('Số line items')
                    ->numeric()
                    ->disabled()
                    ->helperText('Tự động tính từ line items'),

                Forms\Components\Textarea::make('note')
                    ->label('Ghi chú')
                    ->rows(3)
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // ── Plan: tree-aware first column ─────────────────────────────
                Tables\Columns\TextColumn::make('brief.campaign_name')
                    ->label('Plan')
                    ->html()
                    ->formatStateUsing(function (Plan $record) {
                        $isLatest = (int) $record->version === (int) ($record->max_version ?? $record->version);

                        if ($isLatest) {
                            return '<div class="font-semibold text-gray-950 dark:text-white">'
                                . e($record->brief?->campaign_name)
                                . '</div>'
                                . '<div class="text-xs text-gray-400 mt-0.5">'
                                . e($record->plan_no) . ' · v' . $record->version
                                . '</div>';
                        }

                        // Older version row — indented child style
                        return '<div class="flex items-center gap-2 pl-5 text-gray-500 dark:text-gray-400 text-sm">'
                            . '<span class="font-mono leading-none">↳</span>'
                            . '<span>' . e($record->plan_no) . ' · v' . $record->version . '</span>'
                            . '</div>';
                    })
                    ->searchable(true, fn (Builder $query, string $search) =>
                        $query->where('plan_no', 'like', "%{$search}%")
                            ->orWhereHas('brief', fn ($q) => $q->where('campaign_name', 'like', "%{$search}%"))
                    )
                    ->url(fn (Plan $record) => static::getUrl('view', ['record' => $record])),

                Tables\Columns\TextColumn::make('brief.customer.name')
                    ->label('Khách hàng')
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('brief.sale.name')
                    ->label('Sale')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('adops.name')
                    ->label('AdOps')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('budget')
                    ->label('Ngân sách')
                    ->money(fn (Plan $record) => $record->brief?->currency ?? 'VND')
                    ->placeholder('—')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Plan::$statuses[$state] ?? $state)
                    ->color(fn ($state) => Plan::$statusColors[$state] ?? 'gray'),
            ])
            // Mute older-version rows visually
            ->recordClasses(fn (Plan $record) =>
                (int) $record->version === (int) ($record->max_version ?? $record->version)
                    ? null
                    : 'opacity-60 bg-gray-50 dark:bg-white/5'
            )
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options(Plan::$statuses),
            ])
            ->actions([
                // ── Expand / collapse older versions ──────────────────────────
                Tables\Actions\Action::make('toggle_versions')
                    ->iconButton()
                    ->icon(fn (Plan $record, \Livewire\Component $livewire) =>
                        in_array($record->brief_id, $livewire->expandedBriefs ?? [])
                            ? 'heroicon-s-chevron-up'
                            : 'heroicon-s-chevron-down'
                    )
                    ->badge(fn (Plan $record) => (int) ($record->max_version ?? 1))
                    ->badgeColor('gray')
                    ->color('gray')
                    ->tooltip(fn (Plan $record, \Livewire\Component $livewire) =>
                        in_array($record->brief_id, $livewire->expandedBriefs ?? [])
                            ? 'Thu gọn'
                            : 'Xem ' . (int) $record->max_version . ' phiên bản'
                    )
                    ->visible(fn (Plan $record) =>
                        (int) $record->version === (int) ($record->max_version ?? $record->version)
                        && (int) ($record->max_version ?? 1) > 1
                    )
                    ->action(fn (Plan $record, \Livewire\Component $livewire) =>
                        $livewire->toggleBriefExpansion($record->brief_id)
                    ),

                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make()
                        ->visible(fn (Plan $record) => in_array($record->status, ['draft', 're_plan'])
                            && auth()->user()->hasAnyRole(['adops', 'ceo', 'coo'])
                        ),

                    // ── Tạo Booking từ Plan được chấp nhận ────────────────────
                    Tables\Actions\Action::make('create_booking')
                        ->label('Tạo Booking')
                        ->icon('heroicon-o-document-plus')
                        ->color('success')
                        ->visible(fn (Plan $record) =>
                            $record->status === 'accepted'
                            && $record->booking()->doesntExist()
                            && auth()->user()->hasAnyRole(['sale', 'ceo', 'coo'])
                        )
                        ->requiresConfirmation()
                        ->modalHeading('Tạo Booking từ Plan này?')
                        ->modalDescription('Toàn bộ line items của Plan sẽ được sao chép vào Booking. Dữ liệu sẽ là bản final để media buying và finance kiểm soát.')
                        ->action(fn (Plan $record) => static::createBookingFromPlan($record)),
                ]),
            ]);
        // Note: no ->defaultSort() — ordering is handled in ListPlans::getTableQuery()
    }

    // ─── Booking creation logic ───────────────────────────────────────────────

    public static function createBookingFromPlan(Plan $plan): void
    {
        $brief    = $plan->brief;
        $currency = $brief?->currency ?? 'VND';

        $lineItems = $plan->lineItems()->orderBy('sort_order')->get();

        $booking = Booking::create([
            'brief_id'      => $plan->brief_id,
            'plan_id'       => $plan->id,
            'customer_id'   => $brief?->customer_id,
            'sale_id'       => $brief?->sale_id,
            'adops_id'      => $plan->adops_id,
            'campaign_name' => $brief?->campaign_name,
            'currency'      => $currency,
            'start_date'    => $lineItems->min('start_date'),
            'end_date'      => $lineItems->max('end_date'),
            'total_budget'  => $plan->budget,
            'status'        => 'pending_contract',
        ]);

        // Snapshot tất cả line items vào BookingLineItem
        foreach ($lineItems as $index => $item) {
            $networkIds    = $item->targeting ?? [];
            $networkNames  = AdNetwork::whereIn('id', $networkIds)->orderBy('name')->pluck('name')->toArray();

            BookingLineItem::create([
                'booking_id'        => $booking->id,
                'plan_line_item_id' => $item->id,
                'format'            => $item->format,
                'targeting'         => $networkIds,
                'targeting_names'   => $networkNames,
                'start_date'        => $item->start_date,
                'end_date'          => $item->end_date,
                'live_days'         => $item->live_days,
                'unit'              => $item->unit,
                'guaranteed_units'  => $item->guaranteed_units,
                'unit_cost'         => $item->unit_cost,
                'daily_spots'       => $item->daily_spots,
                'line_budget'       => $item->line_budget,
                'est_impression'    => $item->est_impression,
                'est_impression_day'=> $item->est_impression_day,
                'est_ad_spot'       => $item->est_ad_spot,
                'buying_status'     => 'pending',
                'notes'             => $item->notes,
                'sort_order'        => $item->sort_order ?? $index,
            ]);
        }

        // Cập nhật Brief sang trạng thái "Đã tạo Booking"
        $brief?->update(['status' => 'converted']);

        Notification::make()
            ->title("Đã tạo Booking {$booking->booking_no}")
            ->body("{$lineItems->count()} line items đã được sao chép.")
            ->success()
            ->send();
    }

    public static function getRelationManagers(): array
    {
        return [
            RelationManagers\LineItemsRelationManager::class,
            ActivityLogRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPlans::route('/'),
            'create' => Pages\CreatePlan::route('/create'),
            'view'   => Pages\ViewPlan::route('/{record}'),
            'edit'   => Pages\EditPlan::route('/{record}/edit'),
        ];
    }
}
