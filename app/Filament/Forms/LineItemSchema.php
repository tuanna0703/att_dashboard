<?php

namespace App\Filament\Forms;

use App\Models\AdNetwork;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;

/**
 * Shared OOH line item form schema — used by Brief, Plan, and Booking.
 */
class LineItemSchema
{
    /**
     * Full form schema for a single line item (used inside Section or Repeater).
     *
     * @param bool $withNotes  Include notes field
     * @param bool $withHidden Include hidden metadata fields (brief_line_item_id, source, created_by)
     */
    public static function schema(bool $withNotes = true, bool $withHidden = false): array
    {
        $fields = [
            // ── Section 1: Vị trí & Network ──────────────────────────────────
            Forms\Components\Section::make('Vị trí & Network')->schema([
                Forms\Components\Grid::make(5)->schema([
                    Forms\Components\Select::make('targeting')
                        ->label('Network')
                        ->options(fn () => AdNetwork::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->columnSpan(2),
                    Forms\Components\Select::make('format')
                        ->label('Ad Form')
                        ->options([
                            'LCD' => 'LCD', 'LED' => 'LED',
                            'Standee' => 'Standee', 'Poster' => 'Poster',
                            '6s' => '6s', '15s' => '15s', '30s' => '30s',
                        ])
                        ->searchable(),
                    Forms\Components\TextInput::make('city')
                        ->label('Thành phố'),
                    Forms\Components\TextInput::make('qty_location')
                        ->label('Số vị trí')
                        ->numeric()
                        ->default(1)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set) => static::recalc($get, $set)),
                ]),
                Forms\Components\Grid::make(5)->schema([
                    Forms\Components\TextInput::make('qty_screen')
                        ->label('Số LCD')
                        ->numeric()
                        ->default(1)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set) => static::recalc($get, $set)),
                    Forms\Components\TextInput::make('duration_seconds')
                        ->label('TVC (giây)')
                        ->numeric()
                        ->default(15)
                        ->suffix('s'),
                    Forms\Components\TextInput::make('daily_spots')
                        ->label('Spot/ngày')
                        ->numeric()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set) => static::recalc($get, $set)),
                    Forms\Components\TextInput::make('sov')
                        ->label('SOV')
                        ->numeric()
                        ->suffix('%'),
                    Forms\Components\TextInput::make('frequency_minutes')
                        ->label('Tần suất (phút)')
                        ->numeric(),
                ]),
            ])->compact(),

            // ── Section 2: Thời gian phát sóng ──────────────────────────────
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
                        ->afterStateUpdated(fn (Get $get, Set $set) => static::recalc($get, $set)),
                    Forms\Components\DatePicker::make('end_date')
                        ->label('Ngày kết thúc')
                        ->displayFormat('d/m/Y')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set) => static::recalc($get, $set)),
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

            // ── Section 3: Mua & Giá ────────────────────────────────────────
            Forms\Components\Section::make('Mua & Giá')->schema([
                Forms\Components\Grid::make(6)->schema([
                    Forms\Components\TextInput::make('buy_weeks')
                        ->label('Tuần mua')
                        ->numeric()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set) => static::recalc($get, $set)),
                    Forms\Components\TextInput::make('foc_weeks')
                        ->label('Tuần FOC')
                        ->numeric()
                        ->default(0)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set) => static::recalc($get, $set)),
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
                        ->afterStateUpdated(fn (Get $get, Set $set) => static::recalc($get, $set)),
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
                        ->afterStateUpdated(fn (Get $get, Set $set) => static::recalc($get, $set)),
                ]),
                Forms\Components\Grid::make(6)->schema([
                    Forms\Components\TextInput::make('gross_amount')
                        ->label('GROSS Total (VAT)')
                        ->numeric()
                        ->disabled()
                        ->dehydrated(true)
                        ->prefix('₫'),
                ]),
            ])->compact(),

            // ── Section 4: KPI Impressions ──────────────────────────────────
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
                        ->afterStateUpdated(fn (Get $get, Set $set) => static::recalc($get, $set)),
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
     * All OOH field names (for afterCreate / copy operations).
     */
    public static function fieldNames(): array
    {
        return [
            'format', 'targeting',
            'city', 'qty_location', 'qty_screen',
            'start_date', 'end_date', 'live_days',
            'time_from', 'time_to', 'total_hours', 'sov', 'duration_seconds', 'frequency_minutes', 'daily_spots',
            'buy_weeks', 'foc_weeks', 'total_weeks',
            'unit', 'guaranteed_units', 'unit_cost', 'line_budget', 'gross_amount', 'vat_rate',
            'est_impression', 'est_impression_day', 'est_ad_spot', 'kpi_multiplier',
            'notes',
        ];
    }

    /**
     * Recalculate computed fields from form state.
     */
    public static function recalc(Get $get, Set $set): void
    {
        // Live days
        $start = $get('start_date');
        $end   = $get('end_date');
        if ($start && $end) {
            $set('live_days', max(0, Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1));
        }

        // Total weeks
        $buyWeeks   = (int) ($get('buy_weeks') ?? 0);
        $focWeeks   = (int) ($get('foc_weeks') ?? 0);
        $totalWeeks = $buyWeeks + $focWeeks;
        $set('total_weeks', $totalWeeks);

        // NET = qty_location × buy_weeks × unit_cost
        $unitCost    = (float) ($get('unit_cost') ?? 0);
        $qtyLocation = max(1, (int) ($get('qty_location') ?? 1));
        $net         = $qtyLocation * $buyWeeks * $unitCost;
        $set('line_budget', round($net, 2));

        // GROSS = NET × (1 + VAT%)
        $vatRate = (float) ($get('vat_rate') ?? 8);
        $set('gross_amount', round($net * (1 + $vatRate / 100), 2));

        // Ad Spots = daily_spots × qty_screen × total_weeks × 7
        $dailySpots = (int) ($get('daily_spots') ?? 0);
        $qtyScreen  = max(1, (int) ($get('qty_screen') ?? 1));
        $adSpots    = $dailySpots * $qtyScreen * $totalWeeks * 7;
        $set('est_ad_spot', $adSpots);

        // Impression = ad_spots × multiplier
        $multiplier = max(1, (int) ($get('kpi_multiplier') ?? 1));
        $set('est_impression', $adSpots * $multiplier);
    }
}
