<?php

namespace App\Filament\Resources\PlanResource\RelationManagers;

use App\Models\Plan;
use App\Models\Screen;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\RawJs;
use Filament\Tables;
use Filament\Tables\Table;

class LineItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'lineItems';
    protected static ?string $title       = 'Line Items — Chi tiết màn hình';

    public function isReadOnly(): bool
    {
        /** @var \App\Models\Plan $plan */
        $plan = $this->getOwnerRecord();

        // Only AdOps (and admin roles) can edit line items; editable only when plan is draft
        if (! auth()->user()->hasAnyRole(['adops', 'ceo', 'coo'])) {
            return true;
        }

        return ! in_array($plan->status, ['draft', 're_plan']);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            // ── 1. Màn hình ────────────────────────────────────────────────────
            Forms\Components\Section::make('Màn hình')->schema([
                Forms\Components\Select::make('screen_id')
                    ->label('Chọn màn hình')
                    ->options(
                        Screen::active()
                            ->orderBy('code')
                            ->get()
                            ->mapWithKeys(fn (Screen $s) => [
                                $s->id => "[{$s->code}] {$s->name}" . ($s->location_city ? " — {$s->location_city}" : ''),
                            ])
                    )
                    ->searchable()
                    ->nullable()
                    ->live()
                    ->afterStateUpdated(function (?int $state, Set $set) {
                        if (! $state) {
                            return;
                        }
                        $screen = Screen::find($state);
                        if (! $screen) {
                            return;
                        }
                        $set('screen_code',    $screen->code);
                        $set('venue_name',     $screen->venue_name);
                        $set('venue_type',     $screen->venue_type);
                        $set('location_city',  $screen->location_city);
                        $set('spot_duration',  $screen->slot_duration_seconds);
                        $set('spots_per_hour', $screen->total_slots_per_hour);
                        $set('daily_hours',    $screen->operational_hours);
                        if ($screen->rate_card_daily) {
                            $set('pricing_model',    'fixed');
                            $set('rate_card_price',  $screen->rate_card_daily);
                        } elseif ($screen->rate_card_cpm) {
                            $set('pricing_model', 'cpm');
                            $set('cpm',           $screen->rate_card_cpm);
                        }
                    })
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('screen_code')
                    ->label('Mã màn hình')
                    ->maxLength(50),

                Forms\Components\TextInput::make('venue_name')
                    ->label('Tên địa điểm')
                    ->maxLength(200),

                Forms\Components\Select::make('venue_type')
                    ->label('Loại địa điểm')
                    ->options(Screen::$venueTypes),

                Forms\Components\TextInput::make('location_city')
                    ->label('Thành phố')
                    ->maxLength(100),

            ])->columns(2),

            // ── 2. Lịch chạy ───────────────────────────────────────────────────
            Forms\Components\Section::make('Lịch chạy')->schema([
                Forms\Components\DatePicker::make('start_date')
                    ->label('Ngày bắt đầu')
                    ->displayFormat('d/m/Y')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::recalcSpots($get, $set)),

                Forms\Components\DatePicker::make('end_date')
                    ->label('Ngày kết thúc')
                    ->displayFormat('d/m/Y')
                    ->required()
                    ->after('start_date')
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::recalcSpots($get, $set)),

                Forms\Components\TextInput::make('spot_duration')
                    ->label('Thời lượng slot')
                    ->numeric()
                    ->default(15)
                    ->suffix('giây'),

                Forms\Components\TextInput::make('spots_per_hour')
                    ->label('Spots / giờ')
                    ->numeric()
                    ->required()
                    ->default(4)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::recalcSpots($get, $set)),

                Forms\Components\TextInput::make('daily_hours')
                    ->label('Giờ phát / ngày')
                    ->numeric()
                    ->required()
                    ->default(18)
                    ->suffix('giờ')
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::recalcSpots($get, $set)),

                Forms\Components\TextInput::make('total_spots')
                    ->label('Tổng spots')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Tự động tính: spots/giờ × giờ/ngày × số ngày'),

            ])->columns(3),

            // ── 3. Giá ─────────────────────────────────────────────────────────
            Forms\Components\Section::make('Giá & CPM')->schema([
                Forms\Components\Select::make('pricing_model')
                    ->label('Mô hình giá')
                    ->options(['fixed' => 'Fixed (daily)', 'cpm' => 'CPM'])
                    ->default('fixed')
                    ->required(),

                Forms\Components\TextInput::make('rate_card_price')
                    ->label('Giá niêm yết')
                    ->prefix('₫')
                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                    ->dehydrateStateUsing(fn ($state) => $state ? (float) str_replace('.', '', (string) $state) : null)
                    ->afterStateHydrated(function ($component, $state) {
                        if ($state !== null && $state !== '') {
                            $component->state(number_format((float) $state, 0, ',', '.'));
                        }
                    })
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::recalcPrice($get, $set)),

                Forms\Components\TextInput::make('discount_pct')
                    ->label('Chiết khấu (%)')
                    ->numeric()
                    ->default(0)
                    ->suffix('%')
                    ->minValue(0)
                    ->maxValue(100)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::recalcPrice($get, $set)),

                Forms\Components\TextInput::make('net_price')
                    ->label('Giá net')
                    ->prefix('₫')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Tự động: giá niêm yết × (1 − chiết khấu)'),

                Forms\Components\TextInput::make('cpm')
                    ->label('CPM')
                    ->prefix('₫')
                    ->numeric()
                    ->nullable(),

                Forms\Components\TextInput::make('estimated_impressions')
                    ->label('Est. Impressions')
                    ->numeric()
                    ->nullable(),

            ])->columns(3),

            // ── 4. Ghi chú ─────────────────────────────────────────────────────
            Forms\Components\Section::make()->schema([
                Forms\Components\TextInput::make('sort_order')
                    ->label('Thứ tự')
                    ->numeric()
                    ->default(0),

                Forms\Components\Textarea::make('notes')
                    ->label('Ghi chú')
                    ->rows(2)
                    ->columnSpan(2),
            ])->columns(3),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('screen_code')
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('screen_code')
                    ->label('Màn hình')
                    ->weight('bold')
                    ->description(fn ($record) => $record->venue_name)
                    ->searchable(),

                Tables\Columns\TextColumn::make('location_city')
                    ->label('Thành phố')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('venue_type')
                    ->label('Loại')
                    ->formatStateUsing(fn ($state) => Screen::$venueTypes[$state] ?? $state)
                    ->badge()
                    ->color('info')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Từ ngày')
                    ->date('d/m/Y'),

                Tables\Columns\TextColumn::make('end_date')
                    ->label('Đến ngày')
                    ->date('d/m/Y'),

                Tables\Columns\TextColumn::make('spots_per_hour')
                    ->label('Spots/giờ')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('daily_hours')
                    ->label('Giờ/ngày')
                    ->alignCenter()
                    ->suffix('h'),

                Tables\Columns\TextColumn::make('total_spots')
                    ->label('Tổng spots')
                    ->numeric(decimalPlaces: 0)
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('rate_card_price')
                    ->label('Giá niêm yết')
                    ->money('VND')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('discount_pct')
                    ->label('CK%')
                    ->suffix('%')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('net_price')
                    ->label('Giá net')
                    ->money('VND')
                    ->alignEnd()
                    ->weight('bold'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('+ Thêm màn hình'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /** Recalculate total_spots preview in form */
    private static function recalcSpots(Get $get, Set $set): void
    {
        $start       = $get('start_date');
        $end         = $get('end_date');
        $spotsPerHour = (int) $get('spots_per_hour');
        $dailyHours  = (int) $get('daily_hours');

        if ($start && $end && $spotsPerHour && $dailyHours) {
            $days = \Carbon\Carbon::parse($start)->diffInDays(\Carbon\Carbon::parse($end)) + 1;
            $set('total_spots', number_format($spotsPerHour * $dailyHours * $days, 0, ',', '.'));
        }
    }

    /** Recalculate net_price preview in form */
    private static function recalcPrice(Get $get, Set $set): void
    {
        $raw      = str_replace('.', '', (string) $get('rate_card_price'));
        $price    = (float) $raw;
        $discount = (float) $get('discount_pct');

        if ($price > 0) {
            $net = $price * (1 - $discount / 100);
            $set('net_price', number_format($net, 0, ',', '.'));
        }
    }
}
