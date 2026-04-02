<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BriefResource\Pages;
use App\Filament\Resources\BriefResource\RelationManagers;
use App\Models\Brief;
use App\Models\AdNetwork;
use App\Models\BriefLineItem;
use App\Models\Customer;
use App\Models\User;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Actions\Action as NotifAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\RawJs;
use Illuminate\Support\HtmlString;
use Filament\Tables;
use Filament\Tables\Table;

class BriefResource extends Resource
{
    protected static ?string $model = Brief::class;
    protected static ?string $navigationIcon  = 'heroicon-o-document-magnifying-glass';
    protected static ?string $navigationGroup = 'Booking';
    protected static ?int    $navigationSort  = 2;
    protected static ?string $modelLabel      = 'Brief';
    protected static ?string $pluralModelLabel = 'Briefs';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Thông tin campaign')->schema([
                Forms\Components\TextInput::make('campaign_name')
                    ->label('Tên campaign')
                    ->required()
                    ->maxLength(200)
                    ->columnSpanFull(),

                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\Select::make('customer_id')
                        ->label('Khách hàng')
                        ->options(Customer::orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->required(),

                    Forms\Components\Select::make('sale_id')
                        ->label('Sale phụ trách')
                        ->options(User::orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->required()
                        ->default(auth()->id()),

                    Forms\Components\Select::make('adops_id')
                        ->label('AdOps phụ trách')
                        ->options(User::orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->placeholder('Chưa assign'),
                ])->columnSpanFull(),
            ]),

            Forms\Components\Section::make('Line Items')->schema([
                Forms\Components\Repeater::make('briefLineItems')
                    ->relationship('briefLineItems')
                    ->label('')
                    ->schema([
                        // ── Cột trái: thông tin booking ───────────────────
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
                                ->searchable()
                                ->preload()
                                ->placeholder('Tìm và chọn network…'),

                            Forms\Components\DatePicker::make('start_date')
                                ->label('Start Date')
                                ->displayFormat('d/m/Y')
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Get $get, Set $set) => static::recalcLineItem($get, $set)),

                            Forms\Components\DatePicker::make('end_date')
                                ->label('End Date')
                                ->displayFormat('d/m/Y')
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Get $get, Set $set) => static::recalcLineItem($get, $set)),

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
                                ->afterStateUpdated(fn (Get $get, Set $set) => static::recalcLineItem($get, $set)),

                            Forms\Components\TextInput::make('guaranteed_units')
                                ->label(fn (Get $get) => match ($get('unit')) {
                                    'cpd'   => 'Số màn hình',
                                    'io'    => 'Spots/Day',
                                    default => 'Guaranteed Units',
                                })
                                ->numeric()
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Get $get, Set $set) => static::recalcLineItem($get, $set)),

                            Forms\Components\TextInput::make('unit_cost')
                                ->label(fn (Get $get) => match ($get('unit')) {
                                    'cpd'   => 'Rate/Screen/Day',
                                    'io'    => 'Rate/Spot',
                                    default => 'Unit Cost',
                                })
                                ->numeric()
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Get $get, Set $set) => static::recalcLineItem($get, $set)),

                            Forms\Components\TextInput::make('daily_spots')
                                ->label('Daily Spots/Screen')
                                ->helperText('Số spot/màn hình/ngày')
                                ->numeric()
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Get $get, Set $set) => static::recalcLineItem($get, $set))
                                ->hidden(fn (Get $get) => $get('unit') !== 'cpd'),

                            Forms\Components\TextInput::make('line_budget')
                                ->label('Budget')
                                ->numeric()
                                ->disabled()
                                ->dehydrated(true)
                                ->columnSpanFull(),
                        ])->columns(2)->columnSpan(1),

                        // ── Cột phải: Est KPI ──────────────────────────────
                        Forms\Components\Section::make('Est KPI')->schema([
                            Forms\Components\TextInput::make('est_impression')
                                ->label('Est Impression')
                                ->numeric()
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Get $get, Set $set) => static::recalcLineItem($get, $set)),

                            Forms\Components\TextInput::make('est_impression_day')
                                ->label('Est Impression/Day')
                                ->numeric(),

                            Forms\Components\TextInput::make('est_ad_spot')
                                ->label('Est Ad Spot')
                                ->numeric(),
                        ])->columns(1)->columnSpan(1),
                    ])
                    ->columns(2)
                    ->addActionLabel('+ Thêm line item')
                    ->defaultItems(0)
                    ->reorderable('sort_order')
                    ->collapsible()
                    ->live()
                    ->itemLabel(function (array $state): ?string {
                        $networkId = $state['targeting'] ?? null;
                        $network   = $networkId ? AdNetwork::find($networkId)?->name : null;
                        $parts = array_filter([
                            $network,
                            $state['format'] ?? null,
                        ]);
                        return $parts ? implode(' — ', $parts) : 'Line item mới';
                    }),

                // ── Footer: currency + running total ──────────────────────────
                Forms\Components\Grid::make(4)
                    ->schema([
                        Forms\Components\Select::make('currency')
                            ->label('Tiền tệ')
                            ->options(['VND' => 'VND (₫)', 'USD' => 'USD ($)'])
                            ->default('VND')
                            ->required()
                            ->live()
                            ->columnSpan(1),

                        Forms\Components\Placeholder::make('budget_display')
                            ->label('Tổng ngân sách')
                            ->content(function (Get $get): HtmlString {
                                $items    = $get('briefLineItems') ?? [];
                                $total    = collect($items)->sum(fn ($item) => (float) ($item['line_budget'] ?? 0));
                                $currency = $get('currency') ?? 'VND';
                                $formatted = $total > 0
                                    ? number_format($total, 0, ',', '.') . ' ' . $currency
                                    : '—';
                                return new HtmlString(
                                    '<span class="text-xl font-bold text-primary-600 dark:text-primary-400">'
                                    . e($formatted) .
                                    '</span>'
                                );
                            })
                            ->columnSpan(3),
                    ])
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => 'border-t border-gray-200 dark:border-gray-700 pt-4 mt-2',
                    ]),
            ]),

            Forms\Components\Section::make('Chi tiết yêu cầu')->schema([
                Forms\Components\Textarea::make('note')
                    ->label('Ghi chú')
                    ->rows(2)
                    ->columnSpanFull(),

                Forms\Components\FileUpload::make('file_path')
                    ->label('File đính kèm (brief gốc từ khách)')
                    ->directory('briefs/attachments')
                    ->acceptedFileTypes([
                        'application/pdf',
                        'image/*',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/msword',
                    ])
                    ->columnSpanFull(),
            ]),
        ]);
    }

    private static function recalcLineItem(Get $get, Set $set): void
    {
        $unit       = $get('unit') ?? 'cpm';
        $guaranteed = (int) ($get('guaranteed_units') ?? 0);
        $unitCost   = (float) ($get('unit_cost') ?? 0);
        $dailySpots = (int) ($get('daily_spots') ?? 0);
        $multiplier = 1;

        // live_days từ date range
        $start    = $get('start_date');
        $end      = $get('end_date');
        $liveDays = 0;
        if ($start && $end) {
            $liveDays = max(0, Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1);
            $set('live_days', $liveDays);
        }

        // line_budget
        $budget = match ($unit) {
            'cpm'   => $guaranteed * $unitCost,                    // units × unit cost
            'cpd'   => $guaranteed * $unitCost * $liveDays,        // screens × rate/screen/day × days
            'io'    => $guaranteed * $liveDays * $unitCost,        // spots/day × days × rate/spot
            default => 0,
        };
        $set('line_budget', round($budget, 2));

        // est_ad_spot
        $adSpot = match ($unit) {
            'cpm'   => $multiplier > 0 ? (int) round($guaranteed / $multiplier) : 0,
            'cpd'   => $guaranteed * $dailySpots * $liveDays,
            'io'    => $guaranteed * $liveDays,
            default => 0,
        };

        // est_impression — nếu user không nhập thủ công thì tính từ adSpot × multiplier
        $impression = (int) ($get('est_impression') ?? 0);
        if ($impression === 0) {
            $impression = match ($unit) {
                'cpm'       => $guaranteed,
                'cpd', 'io' => $adSpot * $multiplier,
                default     => 0,
            };
            $set('est_impression', $impression);
        }

        $set('est_ad_spot', $adSpot);
        $set('est_impression_day', $liveDays > 0 ? (int) round($impression / $liveDays) : 0);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('brief_no')
                    ->label('Mã Brief')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('campaign_name')
                    ->label('Tên Campaign')
                    ->searchable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Khách hàng')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sale.name')
                    ->label('Sale')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('adops.name')
                    ->label('AdOps')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Bắt đầu')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_date')
                    ->label('Kết thúc')
                    ->date('d/m/Y'),

                Tables\Columns\TextColumn::make('budget')
                    ->label('Ngân sách')
                    ->formatStateUsing(fn ($state, $record) => $state
                        ? number_format((float) $state, 0, ',', '.') . ' ' . ($record->currency ?? 'VND')
                        : '—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('revisions_count')
                    ->label('Rev.')
                    ->counts('revisions')
                    ->alignCenter(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Trạng thái')
                    ->formatStateUsing(fn ($state) => Brief::$statuses[$state] ?? $state)
                    ->colors(Brief::$statusColors),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options(Brief::$statuses),

                Tables\Filters\SelectFilter::make('customer_id')
                    ->label('Khách hàng')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('sale_id')
                    ->label('Sale')
                    ->relationship('sale', 'name')
                    ->searchable(),

                Tables\Filters\SelectFilter::make('adops_id')
                    ->label('AdOps')
                    ->relationship('adops', 'name')
                    ->searchable(),

                Tables\Filters\Filter::make('assigned_to_me')
                    ->label('Assign cho tôi')
                    ->query(fn ($query) => $query->where('adops_id', auth()->id()))
                    ->toggle(),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make()
                        ->visible(fn (Brief $record) => in_array($record->status, ['draft', 'customer_feedback'])),

                    // Gửi brief cho AdOps
                    Tables\Actions\Action::make('send_to_adops')
                        ->label('Gửi AdOps')
                        ->icon('heroicon-o-arrow-right-circle')
                        ->color('info')
                        ->visible(fn (Brief $record) => $record->status === 'draft')
                        ->form([
                            Forms\Components\Select::make('adops_id')
                                ->label('Assign cho AdOps')
                                ->options(User::orderBy('name')->pluck('name', 'id'))
                                ->required()
                                ->searchable(),
                        ])
                        ->modalHeading('Gửi Brief cho AdOps')
                        ->action(function (Brief $record, array $data) {
                            $record->update([
                                'status'   => 'sent_to_adops',
                                'adops_id' => $data['adops_id'],
                            ]);
                            Notification::make()->title('Đã gửi Brief cho AdOps')->success()->send();

                            $adopsUser = User::find($data['adops_id']);
                            if ($adopsUser) {
                                Notification::make()
                                    ->title('Brief mới được assign cho bạn')
                                    ->body("{$record->brief_no} — {$record->campaign_name}")
                                    ->icon('heroicon-o-document-magnifying-glass')
                                    ->iconColor('info')
                                    ->actions([
                                        NotifAction::make('view')
                                            ->label('Xem Brief')
                                            ->url(static::getUrl('view', ['record' => $record])),
                                    ])
                                    ->sendToDatabase($adopsUser);
                            }
                        }),

                    // Convert sang Booking (khi đã confirmed)
                    Tables\Actions\Action::make('convert_to_booking')
                        ->label('Tạo Booking')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->color('primary')
                        ->visible(fn (Brief $record) => $record->status === 'confirmed')
                        ->requiresConfirmation()
                        ->modalHeading('Chuyển Brief thành Booking?')
                        ->modalDescription('Revision cuối cùng (is_final) sẽ được áp dụng cho Booking.')
                        ->action(function (Brief $record) {
                            $acceptedPlan  = $record->plans()->where('status', 'accepted')->latest()->first();
                            $finalRevision = $record->revisions()->where('is_final', true)->first();
                            $source        = $acceptedPlan ?? $record;

                            $booking = \App\Models\Booking::create([
                                'brief_id'          => $record->id,
                                'brief_revision_id' => $finalRevision?->id,
                                'plan_id'           => $acceptedPlan?->id,
                                'customer_id'       => $record->customer_id,
                                'sale_id'           => $record->sale_id,
                                'adops_id'          => $record->adops_id,
                                'campaign_name'     => $source->campaign_name,
                                'start_date'        => $source->start_date,
                                'end_date'          => $source->end_date,
                                'total_budget'      => $source->budget,
                                'status'            => 'pending_contract',
                            ]);
                            $record->update(['status' => 'converted']);
                            Notification::make()
                                ->title("Đã tạo Booking {$booking->booking_no}")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\DeleteAction::make()
                        ->visible(fn (Brief $record) => $record->status === 'draft'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelationManagers(): array
    {
        return [
            RelationManagers\PlansRelationManager::class,
            RelationManagers\RevisionsRelationManager::class,
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasPermissionTo('briefs.viewAny');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->hasPermissionTo('briefs.create');
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasPermissionTo('briefs.update');
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasPermissionTo('briefs.delete');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListBriefs::route('/'),
            'create' => Pages\CreateBrief::route('/create'),
            'view'   => Pages\ViewBrief::route('/{record}'),
            'edit'   => Pages\EditBrief::route('/{record}/edit'),
        ];
    }
}
