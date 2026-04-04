<?php

namespace App\Filament\Resources\PlanResource\RelationManagers;

use App\Models\AdNetwork;
use App\Models\BriefLineItem;
use App\Models\PlanLineItem;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
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
                ->placeholder('— Không liên kết (item mới của AdOps) —')
                ->columnSpanFull(),

            // ── Cột trái: thông tin booking ───────────────────────────────────
            Forms\Components\Section::make()->schema([
                Forms\Components\Select::make('format')
                    ->label('Format')
                    ->options(['6s' => '6s', '15s' => '15s', '30s' => '30s'])
                    ->placeholder('Chọn format…'),

                Forms\Components\Select::make('targeting')
                    ->label('Network')
                    ->options(fn () => AdNetwork::where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->placeholder('Tìm và chọn network…'),

                Forms\Components\DatePicker::make('start_date')
                    ->label('Start Date')
                    ->displayFormat('d/m/Y')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) =>
                        self::recalcLineItem($get, $set)
                    ),

                Forms\Components\DatePicker::make('end_date')
                    ->label('End Date')
                    ->displayFormat('d/m/Y')
                    ->required()
                    ->afterOrEqual('start_date')
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) =>
                        self::recalcLineItem($get, $set)
                    ),

                Forms\Components\TextInput::make('live_days')
                    ->label('Live Days')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(true),

                Forms\Components\Select::make('unit')
                    ->label('Unit')
                    ->options(BriefLineItem::$units)
                    ->default('cpm')
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) =>
                        self::recalcLineItem($get, $set)
                    ),

                Forms\Components\TextInput::make('guaranteed_units')
                    ->label(fn (Forms\Get $get) => match ($get('unit')) {
                        'cpd'   => 'Số màn hình',
                        'io'    => 'Spots/Day',
                        default => 'Guaranteed Units',
                    })
                    ->numeric()
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) =>
                        self::recalcLineItem($get, $set)
                    ),

                Forms\Components\TextInput::make('unit_cost')
                    ->label(fn (Forms\Get $get) => match ($get('unit')) {
                        'cpd'   => 'Rate/Screen/Day',
                        'io'    => 'Rate/Spot',
                        default => 'Unit Cost',
                    })
                    ->numeric()
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) =>
                        self::recalcLineItem($get, $set)
                    ),

                Forms\Components\TextInput::make('daily_spots')
                    ->label('Daily Spots/Screen')
                    ->helperText('Số spot/màn hình/ngày')
                    ->numeric()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) =>
                        self::recalcLineItem($get, $set)
                    )
                    ->hidden(fn (Forms\Get $get) => $get('unit') !== 'cpd'),

                Forms\Components\TextInput::make('line_budget')
                    ->label('Budget')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false)
                    ->columnSpanFull(),

                Forms\Components\Textarea::make('notes')
                    ->label('Ghi chú')
                    ->rows(2)
                    ->columnSpanFull(),
            ])->columns(2)->columnSpan(1),

            // ── Cột phải: Est KPI ─────────────────────────────────────────────
            Forms\Components\Section::make('Est KPI')->schema([
                Forms\Components\TextInput::make('est_impression')
                    ->label('Est Impression')
                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                    ->live(onBlur: true)
                    ->dehydrateStateUsing(fn ($state) => $state ? (int) preg_replace('/\D/', '', $state) : null),

                Forms\Components\TextInput::make('est_impression_day')
                    ->label('Est Impression/Day')
                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                    ->live(onBlur: true)
                    ->dehydrateStateUsing(fn ($state) => $state ? (int) preg_replace('/\D/', '', $state) : null),

                Forms\Components\TextInput::make('est_ad_spot')
                    ->label('Est Ad Spot')
                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                    ->live(onBlur: true)
                    ->dehydrateStateUsing(fn ($state) => $state ? (int) preg_replace('/\D/', '', $state) : null),
            ])->columns(1)->columnSpan(1),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('format')
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\BadgeColumn::make('source')
                    ->label('Nguồn')
                    ->formatStateUsing(fn ($state) => PlanLineItem::$sourceLabels[$state] ?? $state)
                    ->colors(PlanLineItem::$sourceColors),

                Tables\Columns\TextColumn::make('format')
                    ->label('Format')
                    ->weight('bold')
                    ->description(fn ($record) => AdNetwork::whereIn('id', $record->targeting ?? [])
                        ->orderBy('name')->pluck('name')->implode(', '))
                    ->searchable(),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Từ ngày')
                    ->date('d/m/Y'),

                Tables\Columns\TextColumn::make('end_date')
                    ->label('Đến ngày')
                    ->date('d/m/Y'),

                Tables\Columns\TextColumn::make('unit')
                    ->label('Đơn vị')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('guaranteed_units')
                    ->label('KPI')
                    ->numeric(decimalPlaces: 0, thousandsSeparator: '.')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('unit_cost')
                    ->label('Đơn giá')
                    ->money(fn (PlanLineItem $record) => $record->plan?->brief?->currency ?? 'VND')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('line_budget')
                    ->label('Ngân sách')
                    ->money(fn (PlanLineItem $record) => $record->plan?->brief?->currency ?? 'VND')
                    ->alignEnd()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('Người tạo')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Trạng thái')
                    ->formatStateUsing(fn ($state) => PlanLineItem::$statuses[$state] ?? $state)
                    ->colors(PlanLineItem::$statusColors),
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
                    // ── Confirm (any party can confirm a pending item) ───────
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

                    // ── Reject (can reject the OTHER party's items) ──────────
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

                    // ── Reopen rejected item (original creator only) ─────────
                    Tables\Actions\Action::make('reopen')
                        ->label('Mở lại')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('warning')
                        ->visible(fn (PlanLineItem $record) =>
                            $record->status === 'rejected'
                            && $record->created_by === auth()->id()
                        )
                        ->requiresConfirmation()
                        ->modalHeading('Mở lại line item này?')
                        ->action(function (PlanLineItem $record) {
                            $record->update([
                                'status'           => 'pending',
                                'rejected_by'      => null,
                                'rejected_at'      => null,
                                'rejection_reason' => null,
                            ]);
                            Notification::make()->title('Line item đã được mở lại')->success()->send();
                        }),

                    // ── Edit (own items only, while pending) ─────────────────
                    Tables\Actions\EditAction::make()
                        ->visible(fn (PlanLineItem $record) =>
                            $record->created_by === auth()->id()
                            && $record->status === 'pending'
                        ),

                    // ── Delete (own items only) ──────────────────────────────
                    Tables\Actions\DeleteAction::make()
                        ->visible(fn (PlanLineItem $record) =>
                            $record->created_by === auth()->id()
                        ),
                ]),
            ]);
    }

    private static function recalcLineItem(Forms\Get $get, Forms\Set $set): void
    {
        $unit       = $get('unit') ?? 'cpm';
        $guaranteed = (int) ($get('guaranteed_units') ?? 0);
        $unitCost   = (float) ($get('unit_cost') ?? 0);

        $start    = $get('start_date');
        $end      = $get('end_date');
        $liveDays = 0;
        if ($start && $end) {
            $liveDays = max(0, Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1);
            $set('live_days', $liveDays);
        }

        $budget = match ($unit) {
            'cpm'   => $guaranteed * $unitCost,
            'cpd'   => $guaranteed * $unitCost * $liveDays,
            'io'    => $guaranteed * $liveDays * $unitCost,
            default => 0,
        };
        $set('line_budget', round($budget, 2));
    }
}
