<?php

namespace App\Filament\Forms;

use App\Models\AdNetwork;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;

/**
 * Shared OOH line item form schema — supports both I/O Booking and CPM modes.
 */
class LineItemSchema
{
    /**
     * Full form schema for a single line item.
     *
     * @param string $buyingModel 'io' or 'cpm'
     * @param bool   $withNotes   Include notes field
     * @param bool   $withHidden  Include hidden metadata fields
     */
    public static function schema(string $buyingModel = 'io', bool $withNotes = true, bool $withHidden = false): array
    {
        return $buyingModel === 'cpm'
            ? static::cpmSchema($withNotes, $withHidden)
            : static::ioSchema($withNotes, $withHidden);
    }

    // ─── I/O Booking Schema ──────────────────────────────────────────────────

    private static function ioSchema(bool $withNotes, bool $withHidden): array
    {
        $fields = [
            // ── 1. Network ───────────────────────────────────────────────────
            Forms\Components\Section::make('Network')->schema([
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\Select::make('targeting')
                        ->label('Network')
                        ->options(fn () => AdNetwork::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                        ->multiple()
                        ->searchable()
                        ->preload(),
                    Forms\Components\Select::make('format')
                        ->label('Screen Type')
                        ->options(['LCD' => 'LCD', 'LED' => 'LED', 'Standee' => 'Standee', 'Poster' => 'Poster'])
                        ->searchable(),
                    Forms\Components\TextInput::make('city')
                        ->label('Vị trí'),
                ]),
            ])->compact(),

            // ── 2. Thời gian phát sóng ───────────────────────────────────────
            Forms\Components\Section::make('Thời gian phát sóng')->schema([
                Forms\Components\Grid::make(6)->schema([
                    Forms\Components\TextInput::make('time_from')
                        ->label('Giờ phát')
                        ->placeholder('08:00')
                        ->default('08:00'),
                    Forms\Components\TextInput::make('time_to')
                        ->label('Giờ kết thúc')
                        ->placeholder('22:00')
                        ->default('22:00'),
                    Forms\Components\DatePicker::make('start_date')
                        ->label('Ngày bắt đầu')
                        ->displayFormat('d/m/Y')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set) => static::recalcIO($get, $set)),
                    Forms\Components\DatePicker::make('end_date')
                        ->label('Ngày kết thúc')
                        ->displayFormat('d/m/Y')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set) => static::recalcIO($get, $set)),
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
            ])->compact(),

            // ── 3. Mua & Giá ─────────────────────────────────────────────────
            Forms\Components\Section::make('Mua & Giá')->schema([
                Forms\Components\Grid::make(6)->schema([
                    Forms\Components\TextInput::make('qty_location')
                        ->label('Số vị trí')
                        ->numeric()
                        ->default(1)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set) => static::recalcIO($get, $set)),
                    Forms\Components\TextInput::make('qty_screen')
                        ->label('Số màn hình')
                        ->numeric()
                        ->default(1)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set) => static::recalcIO($get, $set)),
                    Forms\Components\TextInput::make('buy_weeks')
                        ->label('Tuần mua')
                        ->numeric()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set) => static::recalcIO($get, $set)),
                    Forms\Components\TextInput::make('foc_weeks')
                        ->label('Tuần FOC')
                        ->numeric()
                        ->default(0)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set) => static::recalcIO($get, $set)),
                    Forms\Components\TextInput::make('total_weeks')
                        ->label('Tổng tuần')
                        ->numeric()
                        ->disabled()
                        ->dehydrated(true),
                    Forms\Components\TextInput::make('unit_cost')
                        ->label('Đơn giá / Tuần')
                        ->numeric()
                        ->prefix('₫')
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set) => static::recalcIO($get, $set)),
                ]),
                Forms\Components\Grid::make(3)->schema([
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
                        ->afterStateUpdated(fn (Get $get, Set $set) => static::recalcIO($get, $set)),
                    Forms\Components\TextInput::make('gross_amount')
                        ->label('GROSS Total (VAT)')
                        ->numeric()
                        ->disabled()
                        ->dehydrated(true)
                        ->prefix('₫'),
                ]),
            ])->compact(),

            // ── 4. KPI ───────────────────────────────────────────────────────
            Forms\Components\Section::make('KPI')->schema([
                Forms\Components\Grid::make(4)->schema([
                    Forms\Components\Select::make('duration_seconds')
                        ->label('TVC (giây)')
                        ->options(['6' => '6s', '15' => '15s', '30' => '30s'])
                        ->default('15'),
                    Forms\Components\TextInput::make('daily_spots')
                        ->label('Spots/Ngày')
                        ->numeric()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set) => static::recalcIO($get, $set)),
                    Forms\Components\TextInput::make('sov')
                        ->label('SOV')
                        ->numeric()
                        ->suffix('%'),
                    Forms\Components\TextInput::make('frequency_minutes')
                        ->label('Tần suất (phút)')
                        ->numeric(),
                ]),
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
                        ->afterStateUpdated(fn (Get $get, Set $set) => static::recalcIO($get, $set)),
                    Forms\Components\TextInput::make('est_impression')
                        ->label('Impression')
                        ->numeric()
                        ->disabled()
                        ->dehydrated(true),
                    Forms\Components\TextInput::make('est_impression_day')
                        ->label('Impression/Day')
                        ->numeric(),
                ]),
            ])->compact(),
        ];

        return static::appendExtras($fields, $withNotes, $withHidden);
    }

    // ─── CPM Schema ──────────────────────────────────────────────────────────

    private static function cpmSchema(bool $withNotes, bool $withHidden): array
    {
        $fields = [
            Forms\Components\Section::make('Platform & Targeting')->schema([
                Forms\Components\Grid::make(5)->schema([
                    Forms\Components\TextInput::make('platform')
                        ->label('Platform')
                        ->placeholder('Programmatic')
                        ->default('Programmatic'),
                    Forms\Components\TextInput::make('placement')
                        ->label('Placement')
                        ->placeholder('Digital OOH'),
                    Forms\Components\Select::make('format')
                        ->label('Format')
                        ->options(['LCD' => 'LCD', 'LED' => 'LED', '1 TVC 15s' => '1 TVC 15s', '1 TVC 30s' => '1 TVC 30s', '6s' => '6s', '15s' => '15s', '30s' => '30s'])
                        ->searchable(),
                    Forms\Components\TextInput::make('city')
                        ->label('Location')
                        ->placeholder('Vietnam'),
                    Forms\Components\Select::make('targeting')
                        ->label('Targeting')
                        ->options(fn () => AdNetwork::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                        ->multiple()
                        ->searchable()
                        ->preload(),
                ]),
            ])->compact(),

            Forms\Components\Section::make('Buying Model')->schema([
                Forms\Components\Grid::make(5)->schema([
                    Forms\Components\DatePicker::make('start_date')
                        ->label('Start Date')
                        ->displayFormat('d/m/Y')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set) => static::recalcCPM($get, $set)),
                    Forms\Components\DatePicker::make('end_date')
                        ->label('End Date')
                        ->displayFormat('d/m/Y')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set) => static::recalcCPM($get, $set)),
                    Forms\Components\TextInput::make('live_days')
                        ->label('Live Days')
                        ->numeric()
                        ->disabled()
                        ->dehydrated(true),
                    Forms\Components\Select::make('unit')
                        ->label('Unit')
                        ->options(['cpm' => 'CPM', 'cpv' => 'CPV', 'cpc' => 'CPC'])
                        ->default('cpm'),
                    Forms\Components\TextInput::make('guaranteed_units')
                        ->label('Guaranteed Units')
                        ->numeric()
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set) => static::recalcCPM($get, $set)),
                ]),
            ])->compact(),

            Forms\Components\Section::make('Pricing')->schema([
                Forms\Components\Grid::make(4)->schema([
                    Forms\Components\TextInput::make('unit_cost')
                        ->label('Unit Cost (Rate)')
                        ->numeric()
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set) => static::recalcCPM($get, $set)),
                    Forms\Components\TextInput::make('line_budget')
                        ->label('Budget (NET)')
                        ->numeric()
                        ->disabled()
                        ->dehydrated(true),
                    Forms\Components\TextInput::make('vat_rate')
                        ->label('VAT')
                        ->numeric()
                        ->default(8)
                        ->suffix('%')
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set) => static::recalcCPM($get, $set)),
                    Forms\Components\TextInput::make('gross_amount')
                        ->label('GROSS (VAT)')
                        ->numeric()
                        ->disabled()
                        ->dehydrated(true),
                ]),
            ])->compact(),

            Forms\Components\Section::make('Est KPI')->schema([
                Forms\Components\Grid::make(4)->schema([
                    Forms\Components\TextInput::make('est_impression')
                        ->label('Impression')
                        ->numeric()
                        ->disabled()
                        ->dehydrated(true),
                    Forms\Components\TextInput::make('est_impression_day')
                        ->label('Est Impression/Day')
                        ->numeric()
                        ->disabled()
                        ->dehydrated(true),
                    Forms\Components\TextInput::make('kpi_multiplier')
                        ->label('Avg Multiplier')
                        ->numeric()
                        ->default(2)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set) => static::recalcCPM($get, $set)),
                    Forms\Components\TextInput::make('est_ad_spot')
                        ->label('Est Ad Spot')
                        ->numeric()
                        ->disabled()
                        ->dehydrated(true),
                ]),
            ])->compact(),
        ];

        return static::appendExtras($fields, $withNotes, $withHidden);
    }

    // ─── Shared helpers ──────────────────────────────────────────────────────

    private static function appendExtras(array $fields, bool $withNotes, bool $withHidden): array
    {
        if ($withNotes) {
            $fields[] = Forms\Components\Textarea::make('notes')
                ->label('Ghi chú')
                ->rows(1)
                ->nullable()
                ->columnSpanFull();
        }
        if ($withHidden) {
            $fields[] = Forms\Components\Hidden::make('brief_line_item_id');
            $fields[] = Forms\Components\Hidden::make('source');
            $fields[] = Forms\Components\Hidden::make('created_by');
        }
        return $fields;
    }

    /**
     * All field names (for copy operations).
     */
    public static function fieldNames(): array
    {
        return [
            'format', 'platform', 'placement', 'targeting',
            'city', 'qty_location', 'qty_screen',
            'start_date', 'end_date', 'live_days',
            'time_from', 'time_to', 'total_hours', 'sov', 'duration_seconds', 'frequency_minutes', 'daily_spots',
            'buy_weeks', 'foc_weeks', 'total_weeks',
            'unit', 'guaranteed_units', 'unit_cost', 'line_budget', 'gross_amount', 'vat_rate',
            'est_impression', 'est_impression_day', 'est_ad_spot', 'kpi_multiplier',
            'notes',
        ];
    }

    // ─── I/O Recalc ──────────────────────────────────────────────────────────

    public static function recalcIO(Get $get, Set $set): void
    {
        $start = $get('start_date');
        $end   = $get('end_date');
        if ($start && $end) {
            $set('live_days', max(0, Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1));
        }

        $buyWeeks   = (int) ($get('buy_weeks') ?? 0);
        $focWeeks   = (int) ($get('foc_weeks') ?? 0);
        $totalWeeks = $buyWeeks + $focWeeks;
        $set('total_weeks', $totalWeeks);

        $unitCost    = (float) ($get('unit_cost') ?? 0);
        $qtyLocation = max(1, (int) ($get('qty_location') ?? 1));
        $net         = $qtyLocation * $buyWeeks * $unitCost;
        $set('line_budget', round($net, 2));

        $vatRate = (float) ($get('vat_rate') ?? 8);
        $set('gross_amount', round($net * (1 + $vatRate / 100), 2));

        $dailySpots = (int) ($get('daily_spots') ?? 0);
        $qtyScreen  = max(1, (int) ($get('qty_screen') ?? 1));
        $adSpots    = $dailySpots * $qtyScreen * $totalWeeks * 7;
        $set('est_ad_spot', $adSpots);

        $multiplier = max(1, (int) ($get('kpi_multiplier') ?? 1));
        $set('est_impression', $adSpots * $multiplier);
    }

    // ─── CPM Recalc ──────────────────────────────────────────────────────────

    public static function recalcCPM(Get $get, Set $set): void
    {
        $start = $get('start_date');
        $end   = $get('end_date');
        $liveDays = 0;
        if ($start && $end) {
            $liveDays = max(0, Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1);
            $set('live_days', $liveDays);
        }

        $guaranteed = (float) ($get('guaranteed_units') ?? 0);
        $unitCost   = (float) ($get('unit_cost') ?? 0);

        // NET = guaranteed_units × unit_cost
        $net = $guaranteed * $unitCost;
        $set('line_budget', round($net, 2));

        // GROSS
        $vatRate = (float) ($get('vat_rate') ?? 8);
        $set('gross_amount', round($net * (1 + $vatRate / 100), 2));

        // Impression = guaranteed_units × 1000
        $impression = (int) ($guaranteed * 1000);
        $set('est_impression', $impression);

        // Impression/day
        $set('est_impression_day', $liveDays > 0 ? (int) round($impression / $liveDays) : 0);

        // Ad Spot = impression ÷ multiplier
        $multiplier = max(1, (int) ($get('kpi_multiplier') ?? 2));
        $set('est_ad_spot', $impression > 0 ? (int) round($impression / $multiplier) : 0);
    }
}
