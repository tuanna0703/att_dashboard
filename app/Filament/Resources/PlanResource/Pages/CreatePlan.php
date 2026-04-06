<?php

namespace App\Filament\Resources\PlanResource\Pages;

use App\Events\Plan\PlanCreated;
use App\Filament\Resources\PlanResource;
use App\Models\AdNetwork;
use App\Models\Brief;
use App\Models\BriefLineItem;
use App\Models\Plan;
use App\Models\PlanLineItem;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\HtmlString;

class CreatePlan extends CreateRecord
{
    protected static string $resource = PlanResource::class;

    public int $briefId = 0;
    protected array $lineItemsData = [];

    // ─── Authorization ────────────────────────────────────────────────────────

    protected function authorizeAccess(): void
    {
        abort_unless(
            auth()->check() && auth()->user()->hasAnyRole(['adops', 'ceo', 'coo']),
            403
        );
    }

    // ─── Mount: load Brief and pre-fill form ──────────────────────────────────

    public function mount(): void
    {
        $this->briefId = (int) request()->query('brief_id', 0);
        abort_if($this->briefId === 0, 404);

        parent::mount();

        $brief      = Brief::with(['customer', 'sale', 'briefLineItems'])->findOrFail($this->briefId);
        $fromPlanId = (int) request()->query('from_plan_id', 0);

        if ($fromPlanId) {
            $sourcePlan    = Plan::with('lineItems')->find($fromPlanId);
            $lineItemsData = $sourcePlan
                ? $sourcePlan->lineItems
                    ->where('status', '!=', 'rejected')
                    ->values()
                    ->map(fn (PlanLineItem $item) => self::itemToArray($item))
                    ->toArray()
                : [];
        } else {
            $lineItemsData = $brief->briefLineItems
                ->values()
                ->map(fn (BriefLineItem $item) => [
                    'brief_line_item_id' => $item->id,
                    'format'             => $item->format,
                    'targeting'          => $item->targeting ?? [],
                    'city'               => null,
                    'qty_location'       => null,
                    'qty_screen'         => null,
                    'start_date'         => $item->start_date?->format('Y-m-d'),
                    'end_date'           => $item->end_date?->format('Y-m-d'),
                    'live_days'          => $item->live_days,
                    'time_from'          => '08:00',
                    'time_to'            => '22:00',
                    'total_hours'        => 16,
                    'sov'                => null,
                    'duration_seconds'   => 15,
                    'frequency_minutes'  => null,
                    'daily_spots'        => $item->daily_spots ?? null,
                    'buy_weeks'          => null,
                    'foc_weeks'          => 0,
                    'total_weeks'        => null,
                    'unit'               => $item->unit,
                    'guaranteed_units'   => $item->guaranteed_units,
                    'unit_cost'          => $item->unit_cost,
                    'line_budget'        => $item->guaranteed_units && $item->unit_cost
                        ? (float) $item->guaranteed_units * (float) $item->unit_cost
                        : null,
                    'vat_rate'           => 8,
                    'gross_amount'       => null,
                    'est_impression'     => $item->est_impression,
                    'est_impression_day' => $item->est_impression_day ?? null,
                    'est_ad_spot'        => $item->est_ad_spot ?? null,
                    'kpi_multiplier'     => 1,
                    'source'             => 'sale',
                    'created_by'         => $brief->sale_id,
                    'notes'              => null,
                ])
                ->toArray();
        }

        $this->form->fill([
            'brief_id'   => $brief->id,
            'line_items' => $lineItemsData,
        ]);
    }

    private static function itemToArray(PlanLineItem $item): array
    {
        return [
            'brief_line_item_id' => $item->brief_line_item_id,
            'format'             => $item->format,
            'targeting'          => $item->targeting ?? [],
            'city'               => $item->city,
            'qty_location'       => $item->qty_location,
            'qty_screen'         => $item->qty_screen,
            'start_date'         => $item->start_date?->format('Y-m-d'),
            'end_date'           => $item->end_date?->format('Y-m-d'),
            'live_days'          => $item->live_days,
            'time_from'          => $item->time_from,
            'time_to'            => $item->time_to,
            'total_hours'        => $item->total_hours,
            'sov'                => $item->sov,
            'duration_seconds'   => $item->duration_seconds,
            'frequency_minutes'  => $item->frequency_minutes,
            'daily_spots'        => $item->daily_spots,
            'buy_weeks'          => $item->buy_weeks,
            'foc_weeks'          => $item->foc_weeks,
            'total_weeks'        => $item->total_weeks,
            'unit'               => $item->unit,
            'guaranteed_units'   => $item->guaranteed_units,
            'unit_cost'          => $item->unit_cost,
            'line_budget'        => $item->line_budget,
            'vat_rate'           => $item->vat_rate,
            'gross_amount'       => $item->gross_amount,
            'est_impression'     => $item->est_impression,
            'est_impression_day' => $item->est_impression_day,
            'est_ad_spot'        => $item->est_ad_spot,
            'kpi_multiplier'     => $item->kpi_multiplier,
            'source'             => $item->source,
            'created_by'         => $item->created_by,
            'notes'              => $item->notes,
        ];
    }

    // ─── Page title ───────────────────────────────────────────────────────────

    public function getTitle(): string
    {
        $brief = Brief::find($this->briefId);
        return 'Tạo Plan — ' . ($brief?->campaign_name ?? 'Brief #' . $this->briefId);
    }

    // ─── Form ─────────────────────────────────────────────────────────────────

    public function form(Form $form): Form
    {
        $brief = Brief::with(['customer', 'sale', 'briefLineItems'])->find($this->briefId);

        $briefStart = $brief?->briefLineItems->min('start_date');
        $briefEnd   = $brief?->briefLineItems->max('end_date');
        $dateRange  = ($briefStart && $briefEnd)
            ? Carbon::parse($briefStart)->format('d/m/Y') . ' → ' . Carbon::parse($briefEnd)->format('d/m/Y')
            : '—';

        return $form->schema([

            // ── 1. Brief info ────────────────────────────────────────────────
            Forms\Components\Section::make('Thông tin Brief')
                ->description(new HtmlString('Dữ liệu từ Brief — AdOps điều chỉnh các line items bên dưới'))
                ->icon('heroicon-o-document-text')
                ->collapsible()
                ->schema([
                    Forms\Components\Placeholder::make('_campaign_name')
                        ->label('Tên Campaign')
                        ->content($brief?->campaign_name ?? '—'),
                    Forms\Components\Placeholder::make('_customer')
                        ->label('Khách hàng')
                        ->content($brief?->customer?->name ?? '—'),
                    Forms\Components\Placeholder::make('_sale')
                        ->label('Sale phụ trách')
                        ->content($brief?->sale?->name ?? '—'),
                    Forms\Components\Placeholder::make('_brief_no')
                        ->label('Mã Brief')
                        ->content($brief?->brief_no ?? '—'),
                    Forms\Components\Placeholder::make('_budget')
                        ->label('Budget yêu cầu')
                        ->content(
                            $brief?->budget
                                ? number_format((float) $brief->budget, 0, ',', '.') . ' ' . ($brief->currency ?? 'VND')
                                : '—'
                        ),
                    Forms\Components\Placeholder::make('_dates')
                        ->label('Thời gian chạy')
                        ->content($dateRange),
                ])
                ->columns(3),

            // ── 2. Hidden brief_id ───────────────────────────────────────────
            Forms\Components\Hidden::make('brief_id'),

            // ── 3. Plan details ──────────────────────────────────────────────
            Forms\Components\Section::make('Thông tin Plan')
                ->icon('heroicon-o-clipboard-document-list')
                ->schema([
                    Forms\Components\Textarea::make('note')
                        ->label('Ghi chú kế hoạch (AdOps)')
                        ->rows(3)
                        ->placeholder('Mô tả chi tiết về điều chỉnh so với Brief...')
                        ->columnSpanFull(),
                    Forms\Components\FileUpload::make('file_path')
                        ->label('File kế hoạch đính kèm')
                        ->directory('plans')
                        ->acceptedFileTypes([
                            'application/pdf', 'image/*',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                        ])
                        ->columnSpanFull(),
                ]),

            // ── 4. Line items repeater ───────────────────────────────────────
            Forms\Components\Section::make('Line Items')
                ->description('Điều chỉnh theo inventory thực tế. Có thể thêm mới hoặc xoá bỏ.')
                ->icon('heroicon-o-list-bullet')
                ->schema([
                    Forms\Components\Repeater::make('line_items')
                        ->label('')
                        ->schema([
                            // ── Section 1: Vị trí & Network ─────────────────
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
                                        ->options(['LCD' => 'LCD', 'LED' => 'LED', 'Standee' => 'Standee', 'Poster' => 'Poster', '6s' => '6s', '15s' => '15s', '30s' => '30s'])
                                        ->searchable(),
                                    Forms\Components\TextInput::make('city')
                                        ->label('Thành phố'),
                                    Forms\Components\TextInput::make('qty_location')
                                        ->label('Số vị trí')
                                        ->numeric()
                                        ->default(1),
                                ]),
                                Forms\Components\Grid::make(5)->schema([
                                    Forms\Components\TextInput::make('qty_screen')
                                        ->label('Số LCD')
                                        ->numeric()
                                        ->default(1)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn (Get $get, Set $set) => self::recalcLineItem($get, $set)),
                                    Forms\Components\TextInput::make('duration_seconds')
                                        ->label('TVC (giây)')
                                        ->numeric()
                                        ->default(15)
                                        ->suffix('s'),
                                    Forms\Components\TextInput::make('daily_spots')
                                        ->label('Spot/ngày')
                                        ->numeric()
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn (Get $get, Set $set) => self::recalcLineItem($get, $set)),
                                    Forms\Components\TextInput::make('sov')
                                        ->label('SOV')
                                        ->numeric()
                                        ->suffix('%'),
                                    Forms\Components\TextInput::make('frequency_minutes')
                                        ->label('Tần suất (phút)')
                                        ->numeric(),
                                ]),
                            ])->compact(),

                            // ── Section 2: Thời gian ────────────────────────
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
                                        ->afterStateUpdated(fn (Get $get, Set $set) => self::recalcLineItem($get, $set)),
                                    Forms\Components\DatePicker::make('end_date')
                                        ->label('Ngày kết thúc')
                                        ->displayFormat('d/m/Y')
                                        ->required()
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn (Get $get, Set $set) => self::recalcLineItem($get, $set)),
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

                            // ── Section 3: Mua & Giá ────────────────────────
                            Forms\Components\Section::make('Mua & Giá')->schema([
                                Forms\Components\Grid::make(6)->schema([
                                    Forms\Components\TextInput::make('buy_weeks')
                                        ->label('Tuần mua')
                                        ->numeric()
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn (Get $get, Set $set) => self::recalcLineItem($get, $set)),
                                    Forms\Components\TextInput::make('foc_weeks')
                                        ->label('Tuần FOC')
                                        ->numeric()
                                        ->default(0)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn (Get $get, Set $set) => self::recalcLineItem($get, $set)),
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
                                        ->afterStateUpdated(fn (Get $get, Set $set) => self::recalcLineItem($get, $set)),
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
                                        ->afterStateUpdated(fn (Get $get, Set $set) => self::recalcLineItem($get, $set)),
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

                            // ── Section 4: KPI ──────────────────────────────
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
                                        ->afterStateUpdated(fn (Get $get, Set $set) => self::recalcLineItem($get, $set)),
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

                            // ── Notes + hidden fields ────────────────────────
                            Forms\Components\Textarea::make('notes')
                                ->label('Ghi chú')
                                ->rows(1)
                                ->nullable()
                                ->columnSpanFull(),
                            Forms\Components\Hidden::make('brief_line_item_id'),
                            Forms\Components\Hidden::make('source'),
                            Forms\Components\Hidden::make('created_by'),
                        ])
                        ->addActionLabel('+ Thêm line item')
                        ->cloneable()
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(function (array $state): ?string {
                            $networkIds = $state['targeting'] ?? [];
                            if (is_string($networkIds)) {
                                $networkIds = json_decode($networkIds, true) ?? [];
                            }
                            $networks = !empty($networkIds)
                                ? AdNetwork::whereIn('id', (array) $networkIds)->orderBy('name')->pluck('name')->implode(', ')
                                : null;
                            $city  = $state['city'] ?? null;
                            $parts = array_filter([$networks, $state['format'] ?? null, $city]);
                            return $parts ? implode(' — ', $parts) : 'Line item mới';
                        }),

                    // Tổng ngân sách sẽ tự tính khi Plan được lưu
                ]),
        ]);
    }

    // ─── Form submission ──────────────────────────────────────────────────────

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->lineItemsData = $data['line_items'] ?? [];
        unset($data['line_items']);

        $data['adops_id'] = auth()->id();
        $data['status']   = 'draft';

        return $data;
    }

    protected function afterCreate(): void
    {
        $fields = [
            'brief_line_item_id', 'format', 'targeting',
            'city', 'qty_location', 'qty_screen',
            'start_date', 'end_date', 'live_days',
            'time_from', 'time_to', 'total_hours', 'sov', 'duration_seconds', 'frequency_minutes', 'daily_spots',
            'buy_weeks', 'foc_weeks', 'total_weeks',
            'unit', 'guaranteed_units', 'unit_cost', 'line_budget', 'gross_amount', 'vat_rate',
            'est_impression', 'est_impression_day', 'est_ad_spot', 'kpi_multiplier',
            'notes',
        ];

        foreach ($this->lineItemsData as $index => $item) {
            $data = ['plan_id' => $this->record->id, 'status' => 'pending', 'sort_order' => $index];
            $data['created_by'] = $item['created_by'] ?? auth()->id();
            $data['source']     = $item['source'] ?? 'adops';

            foreach ($fields as $f) {
                $data[$f] = $item[$f] ?? null;
            }

            PlanLineItem::create($data);
        }

        event(new PlanCreated(
            subject: $this->record,
            causer:  auth()->user(),
            context: [
                'plan_no' => $this->record->plan_no,
                'version' => $this->record->version,
            ]
        ));

        Notification::make()
            ->title("Plan {$this->record->plan_no} đã được tạo")
            ->success()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return PlanResource::getUrl('view', ['record' => $this->record]);
    }

    // ─── Auto-recalculate ─────────────────────────────────────────────────────

    private static function recalcLineItem(Get $get, Set $set): void
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

        // NET = unit_cost × buy_weeks × qty_screen
        $unitCost  = (float) ($get('unit_cost') ?? 0);
        $qtyScreen = max(1, (int) ($get('qty_screen') ?? 1));
        $net       = $unitCost * $buyWeeks * $qtyScreen;
        $set('line_budget', round($net, 2));

        // GROSS
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
