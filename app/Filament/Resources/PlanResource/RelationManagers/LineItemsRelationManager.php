<?php

namespace App\Filament\Resources\PlanResource\RelationManagers;

use App\Models\AdNetwork;
use App\Models\BriefLineItem;
use App\Models\PlanLineItem;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\RawJs;
use Filament\Tables;
use Filament\Tables\Table;

class LineItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'lineItems';
    protected static ?string $title       = 'Line Items';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function canCreate(): bool
    {
        return auth()->user()->hasAnyRole(['sale', 'adops', 'ceo', 'coo']);
    }

    public function form(Form $form): Form
    {
        /** @var \App\Models\Plan $plan */
        $plan = $this->getOwnerRecord();

        return $form->schema([
            Forms\Components\Select::make('brief_line_item_id')
                ->label('Liên kết Brief line item')
                ->options(
                    BriefLineItem::where('brief_id', $plan->brief_id)
                        ->get()
                        ->mapWithKeys(fn (BriefLineItem $item) => [
                            $item->id => "[{$item->format}] {$item->start_date?->format('d/m/Y')} → {$item->end_date?->format('d/m/Y')}",
                        ])
                )
                ->searchable()
                ->nullable()
                ->placeholder('— Không liên kết (item mới) —')
                ->columnSpanFull(),

            // ── Row 1: Location ──────────────────────────────────────────────
            Forms\Components\Section::make('Vị trí & Network')->schema([
                Forms\Components\Grid::make(5)->schema([
                    Forms\Components\Select::make('targeting')
                        ->label('Network')
                        ->options(fn () => AdNetwork::where('is_active', true)
                            ->orderBy('name')
                            ->pluck('name', 'id'))
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->columnSpan(2),

                    Forms\Components\Select::make('format')
                        ->label('Ad Form')
                        ->options([
                            'LCD' => 'LCD',
                            'LED' => 'LED',
                            'Standee' => 'Standee',
                            'Poster' => 'Poster',
                            '6s' => '6s',
                            '15s' => '15s',
                            '30s' => '30s',
                        ])
                        ->searchable()
                        ->allowHtml(false),

                    Forms\Components\TextInput::make('city')
                        ->label('Thành phố / Quận'),

                    Forms\Components\TextInput::make('qty_location')
                        ->label('Số vị trí')
                        ->numeric()
                        ->default(1)
                        ->minValue(1),
                ]),
                Forms\Components\Grid::make(5)->schema([
                    Forms\Components\TextInput::make('qty_screen')
                        ->label('Số LCD/Màn hình')
                        ->numeric()
                        ->default(1)
                        ->minValue(1)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::recalc($get, $set)),

                    Forms\Components\TextInput::make('duration_seconds')
                        ->label('TVC (giây)')
                        ->numeric()
                        ->default(15)
                        ->suffix('s'),

                    Forms\Components\TextInput::make('daily_spots')
                        ->label('Spot/ngày')
                        ->numeric()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::recalc($get, $set)),

                    Forms\Components\TextInput::make('sov')
                        ->label('SOV')
                        ->numeric()
                        ->suffix('%'),

                    Forms\Components\TextInput::make('frequency_minutes')
                        ->label('Tần suất (phút)')
                        ->numeric(),
                ]),
            ]),

            // ── Row 2: Airing & Dates ────────────────────────────────────────
            Forms\Components\Section::make('Thời gian phát sóng')->schema([
                Forms\Components\Grid::make(6)->schema([
                    Forms\Components\TimePicker::make('time_from')
                        ->label('Giờ phát')
                        ->seconds(false)
                        ->default('08:00'),

                    Forms\Components\TimePicker::make('time_to')
                        ->label('Giờ kết thúc')
                        ->seconds(false)
                        ->default('22:00'),

                    Forms\Components\DatePicker::make('start_date')
                        ->label('Ngày bắt đầu')
                        ->displayFormat('d/m/Y')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::recalc($get, $set)),

                    Forms\Components\DatePicker::make('end_date')
                        ->label('Ngày kết thúc')
                        ->displayFormat('d/m/Y')
                        ->required()
                        ->afterOrEqual('start_date')
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::recalc($get, $set)),

                    Forms\Components\TextInput::make('total_hours')
                        ->label('Giờ/ngày')
                        ->numeric()
                        ->default(16),

                    Forms\Components\TextInput::make('live_days')
                        ->label('Tổng ngày')
                        ->numeric()
                        ->disabled()
                        ->dehydrated(true),
                ]),
            ]),

            // ── Row 3: Buying weeks & Pricing ────────────────────────────────
            Forms\Components\Section::make('Mua & Giá')->schema([
                Forms\Components\Grid::make(6)->schema([
                    Forms\Components\TextInput::make('buy_weeks')
                        ->label('Tuần mua')
                        ->numeric()
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::recalc($get, $set)),

                    Forms\Components\TextInput::make('foc_weeks')
                        ->label('Tuần FOC')
                        ->numeric()
                        ->default(0)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::recalc($get, $set)),

                    Forms\Components\TextInput::make('total_weeks')
                        ->label('Tổng tuần')
                        ->numeric()
                        ->disabled()
                        ->dehydrated(true),

                    Forms\Components\TextInput::make('unit_cost')
                        ->label('Đơn giá / Tuần')
                        ->numeric()
                        ->required()
                        ->prefix('₫')
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::recalc($get, $set)),

                    Forms\Components\TextInput::make('line_budget')
                        ->label('NET Total')
                        ->numeric()
                        ->disabled()
                        ->dehydrated(true)
                        ->prefix('₫'),

                    Forms\Components\TextInput::make('vat_rate')
                        ->label('VAT')
                        ->numeric()
                        ->default(8)
                        ->suffix('%')
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::recalc($get, $set)),
                ]),
                Forms\Components\Grid::make(6)->schema([
                    Forms\Components\TextInput::make('gross_amount')
                        ->label('GROSS Total (VAT)')
                        ->numeric()
                        ->disabled()
                        ->dehydrated(true)
                        ->prefix('₫'),

                    Forms\Components\Placeholder::make('')->hiddenLabel()->content(''),
                    Forms\Components\Placeholder::make('')->hiddenLabel()->content(''),
                    Forms\Components\Placeholder::make('')->hiddenLabel()->content(''),
                    Forms\Components\Placeholder::make('')->hiddenLabel()->content(''),
                    Forms\Components\Placeholder::make('')->hiddenLabel()->content(''),
                ]),
            ]),

            // ── Row 4: KPI ───────────────────────────────────────────────────
            Forms\Components\Section::make('KPI Impressions')->schema([
                Forms\Components\Grid::make(4)->schema([
                    Forms\Components\TextInput::make('est_ad_spot')
                        ->label('Ad Spots')
                        ->numeric()
                        ->disabled()
                        ->dehydrated(true),

                    Forms\Components\TextInput::make('kpi_multiplier')
                        ->label('Multiplier')
                        ->numeric()
                        ->default(1)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::recalc($get, $set)),

                    Forms\Components\TextInput::make('est_impression')
                        ->label('Impression')
                        ->numeric()
                        ->disabled()
                        ->dehydrated(true),

                    Forms\Components\TextInput::make('est_impression_day')
                        ->label('Impression/Day')
                        ->numeric(),
                ]),
            ]),

            // ── Notes ────────────────────────────────────────────────────────
            Forms\Components\Textarea::make('notes')
                ->label('Ghi chú')
                ->rows(2)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('format')
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                // ── Network / Format / City ─────────────────────────────────
                Tables\Columns\TextColumn::make('format')
                    ->label('Network / Format')
                    ->html()
                    ->getStateUsing(fn (PlanLineItem $record) => (function () use ($record) {
                        $networks = AdNetwork::whereIn('id', $record->targeting ?? [])
                            ->orderBy('name')->pluck('name')->implode(', ');
                        $top = $networks
                            ? '<div class="font-semibold text-gray-950 dark:text-white" style="white-space:normal;word-break:break-word;">' . e($networks) . '</div>'
                            : '';
                        $mid = '<div class="text-sm text-gray-500 dark:text-gray-400">' . e($record->format) . '</div>';
                        $bot = $record->city
                            ? '<div class="text-xs text-gray-400">' . e($record->city) . '</div>'
                            : '';
                        return '<div style="max-width:320px;min-width:0;">' . $top . $mid . $bot . '</div>';
                    })())
                    ->searchable(false)
                    ->wrap(),

                // ── Qty ─────────────────────────────────────────────────────
                Tables\Columns\TextColumn::make('qty_screen')
                    ->label('LCD')
                    ->alignCenter()
                    ->placeholder('—'),

                // ── Weeks ───────────────────────────────────────────────────
                Tables\Columns\TextColumn::make('total_weeks')
                    ->label('Tuần')
                    ->html()
                    ->getStateUsing(fn (PlanLineItem $record) =>
                        ($record->buy_weeks ?? 0) . '<span class="text-xs text-gray-400"> +' . ($record->foc_weeks ?? 0) . ' FOC</span>'
                    )
                    ->alignCenter(),

                // ── Date range ──────────────────────────────────────────────
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Thời gian')
                    ->html()
                    ->getStateUsing(fn (PlanLineItem $record) =>
                        '<span class="tabular-nums text-sm">' . ($record->start_date?->format('d/m/Y') ?? '—') . '</span>'
                        . '<span class="text-gray-400 dark:text-gray-500 text-xs mx-1">→</span>'
                        . '<span class="tabular-nums text-sm">' . ($record->end_date?->format('d/m/Y') ?? '—') . '</span>'
                    ),

                // ── Unit Cost / Week ────────────────────────────────────────
                Tables\Columns\TextColumn::make('unit_cost')
                    ->label('Đơn giá/tuần')
                    ->money(fn (PlanLineItem $record) => $record->plan?->brief?->currency ?? 'VND')
                    ->alignEnd(),

                // ── NET ─────────────────────────────────────────────────────
                Tables\Columns\TextColumn::make('line_budget')
                    ->label('NET')
                    ->money(fn (PlanLineItem $record) => $record->plan?->brief?->currency ?? 'VND')
                    ->alignEnd()
                    ->weight('bold'),

                // ── GROSS ───────────────────────────────────────────────────
                Tables\Columns\TextColumn::make('gross_amount')
                    ->label('GROSS')
                    ->money(fn (PlanLineItem $record) => $record->plan?->brief?->currency ?? 'VND')
                    ->alignEnd()
                    ->color('success'),

                // ── KPI ─────────────────────────────────────────────────────
                Tables\Columns\TextColumn::make('est_impression')
                    ->label('Impression')
                    ->numeric()
                    ->alignEnd()
                    ->placeholder('—'),

                // ── Trạng thái ──────────────────────────────────────────────
                Tables\Columns\TextColumn::make('status')
                    ->label('TT')
                    ->badge()
                    ->formatStateUsing(fn ($state) => PlanLineItem::$statuses[$state] ?? $state)
                    ->color(fn ($state) => PlanLineItem::$statusColors[$state] ?? 'gray'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('+ Thêm line item')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['created_by'] = auth()->id();
                        $data['source']     = auth()->user()->hasRole('adops') ? 'adops' : 'sale';
                        $data['status']     = 'pending';
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('confirm')
                        ->label('Xác nhận OK')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (PlanLineItem $record) => $record->status === 'pending')
                        ->requiresConfirmation()
                        ->modalHeading('Xác nhận line item này đã OK?')
                        ->action(function (PlanLineItem $record) {
                            $record->update([
                                'status'       => 'confirmed',
                                'confirmed_by' => auth()->id(),
                                'confirmed_at' => now(),
                            ]);
                            Notification::make()->title('Line item đã được xác nhận')->success()->send();
                        }),

                    Tables\Actions\Action::make('reject_item')
                        ->label('Từ chối')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (PlanLineItem $record) =>
                            $record->status === 'pending'
                            && $record->created_by !== auth()->id()
                        )
                        ->form([
                            Forms\Components\Textarea::make('rejection_reason')
                                ->label('Lý do từ chối')
                                ->required()
                                ->rows(3),
                        ])
                        ->modalHeading('Từ chối line item này')
                        ->action(function (PlanLineItem $record, array $data) {
                            $record->update([
                                'status'           => 'rejected',
                                'rejected_by'      => auth()->id(),
                                'rejected_at'      => now(),
                                'rejection_reason' => $data['rejection_reason'],
                            ]);
                            Notification::make()->title('Line item đã bị từ chối')->danger()->send();
                        }),

                    Tables\Actions\Action::make('reopen')
                        ->label('Mở lại')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('warning')
                        ->visible(fn (PlanLineItem $record) =>
                            $record->status === 'rejected'
                            && $record->created_by === auth()->id()
                        )
                        ->requiresConfirmation()
                        ->action(function (PlanLineItem $record) {
                            $record->update([
                                'status'           => 'pending',
                                'rejected_by'      => null,
                                'rejected_at'      => null,
                                'rejection_reason' => null,
                            ]);
                            Notification::make()->title('Line item đã được mở lại')->success()->send();
                        }),

                    Tables\Actions\EditAction::make()
                        ->visible(fn (PlanLineItem $record) =>
                            $record->created_by === auth()->id()
                            && $record->status === 'pending'
                        ),

                    Tables\Actions\DeleteAction::make()
                        ->visible(fn (PlanLineItem $record) =>
                            $record->created_by === auth()->id()
                        ),
                ]),
            ]);
    }

    // ─── Auto-recalculate on form ────────────────────────────────────────────

    private static function recalc(Get $get, Set $set): void
    {
        // Live days
        $start = $get('start_date');
        $end   = $get('end_date');
        if ($start && $end) {
            $liveDays = max(0, Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1);
            $set('live_days', $liveDays);
        }

        // Total weeks
        $buyWeeks  = (int) ($get('buy_weeks') ?? 0);
        $focWeeks  = (int) ($get('foc_weeks') ?? 0);
        $totalWeeks = $buyWeeks + $focWeeks;
        $set('total_weeks', $totalWeeks);

        // NET = unit_cost × buy_weeks × qty_screen
        $unitCost  = (float) ($get('unit_cost') ?? 0);
        $qtyScreen = max(1, (int) ($get('qty_screen') ?? 1));
        $net       = $unitCost * $buyWeeks * $qtyScreen;
        $set('line_budget', round($net, 2));

        // GROSS = NET × (1 + VAT%)
        $vatRate = (float) ($get('vat_rate') ?? 8);
        $set('gross_amount', round($net * (1 + $vatRate / 100), 2));

        // Ad Spots = daily_spots × qty_screen × total_weeks × 7
        $dailySpots = (int) ($get('daily_spots') ?? 0);
        $adSpots    = $dailySpots * $qtyScreen * $totalWeeks * 7;
        $set('est_ad_spot', $adSpots);

        // Impression = ad_spots × multiplier
        $multiplier = max(1, (int) ($get('kpi_multiplier') ?? 1));
        $set('est_impression', $adSpots * $multiplier);
    }
}
