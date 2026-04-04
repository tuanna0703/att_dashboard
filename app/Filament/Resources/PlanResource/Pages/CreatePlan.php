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
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\RawJs;
use Illuminate\Support\HtmlString;

class CreatePlan extends CreateRecord
{
    protected static string $resource = PlanResource::class;

    // Store brief_id as a Livewire public property so it survives re-renders
    public int $briefId = 0;

    // Temp storage between mutateFormDataBeforeCreate → afterCreate
    protected array $lineItemsData = [];

    // ─── Authorization ────────────────────────────────────────────────────────

    protected function authorizeAccess(): void
    {
        // Skip the resource canCreate() check (which returns false globally)
        // and enforce role-based access directly
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
            // Pre-populate from a previous plan's line items (create_revision flow)
            $sourcePlan    = Plan::with('lineItems')->find($fromPlanId);
            $lineItemsData = $sourcePlan
                ? $sourcePlan->lineItems
                    ->where('status', '!=', 'rejected')
                    ->values()
                    ->map(fn (PlanLineItem $item) => [
                        'brief_line_item_id' => $item->brief_line_item_id,
                        'format'             => $item->format,
                        'targeting'          => $item->targeting ?? [],
                        'start_date'         => $item->start_date?->format('Y-m-d'),
                        'end_date'           => $item->end_date?->format('Y-m-d'),
                        'live_days'          => $item->live_days,
                        'unit'               => $item->unit,
                        'guaranteed_units'   => $item->guaranteed_units,
                        'unit_cost'          => $item->unit_cost,
                        'daily_spots'        => $item->daily_spots,
                        'line_budget'        => $item->line_budget,
                        'est_impression'     => $item->est_impression,
                        'est_impression_day' => $item->est_impression_day,
                        'est_ad_spot'        => $item->est_ad_spot,
                        'source'             => $item->source,
                        'created_by'         => $item->created_by,
                        'notes'              => $item->notes,
                    ])
                    ->toArray()
                : [];
        } else {
            // Pre-populate from Brief's line items
            $lineItemsData = $brief->briefLineItems
                ->values()
                ->map(fn (BriefLineItem $item) => [
                    'brief_line_item_id' => $item->id,
                    'format'             => $item->format,
                    'targeting'          => $item->targeting ?? [],
                    'start_date'         => $item->start_date?->format('Y-m-d'),
                    'end_date'           => $item->end_date?->format('Y-m-d'),
                    'live_days'          => $item->live_days,
                    'unit'               => $item->unit,
                    'guaranteed_units'   => $item->guaranteed_units,
                    'unit_cost'          => $item->unit_cost,
                    'daily_spots'        => $item->daily_spots ?? null,
                    'line_budget'        => $item->guaranteed_units && $item->unit_cost
                        ? (float) $item->guaranteed_units * (float) $item->unit_cost
                        : null,
                    'est_impression'     => $item->est_impression,
                    'est_impression_day' => $item->est_impression_day ?? null,
                    'est_ad_spot'        => $item->est_ad_spot ?? null,
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

            // ── 1. Brief info (read-only summary) ────────────────────────────
            Forms\Components\Section::make('Thông tin Brief')
                ->description(new HtmlString('Dữ liệu từ Brief — AdOps điều chỉnh các line items bên dưới cho phù hợp với inventory thực tế'))
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

                    Forms\Components\Placeholder::make('_note')
                        ->label('Ghi chú của Sale')
                        ->content($brief?->note ?? '—')
                        ->columnSpanFull(),
                ])
                ->columns(3),

            // ── 2. Hidden brief_id ────────────────────────────────────────────
            Forms\Components\Hidden::make('brief_id'),

            // ── 3. Plan details ───────────────────────────────────────────────
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
                            'application/pdf',
                            'image/*',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'application/msword',
                        ])
                        ->columnSpanFull(),
                ]),

            // ── 4. Line items repeater ────────────────────────────────────────
            Forms\Components\Section::make('Line Items')
                ->description('Xem lại và điều chỉnh KPI thực tế, đơn giá cho từng line item. Có thể thêm mới hoặc xóa bỏ.')
                ->icon('heroicon-o-list-bullet')
                ->schema([
                    Forms\Components\Repeater::make('line_items')
                        ->label('')
                        ->schema([
                            // ── Cột trái: thông tin booking ──────────────────
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
                                    ->rows(1)
                                    ->nullable()
                                    ->columnSpanFull(),

                                // Hidden fields — carry ownership metadata
                                Forms\Components\Hidden::make('brief_line_item_id'),
                                Forms\Components\Hidden::make('source'),
                                Forms\Components\Hidden::make('created_by'),
                            ])->columns(2)->columnSpan(1),

                            // ── Cột phải: Est KPI ─────────────────────────────
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
                        ])
                        ->columns(2)
                        ->addActionLabel('+ Thêm line item')
                        ->cloneable()
                        ->reorderable()
                        ->collapsible()
                        ->live()
                        ->itemLabel(function (array $state): ?string {
                            $networkIds = $state['targeting'] ?? [];
                            if (is_string($networkIds)) {
                                $networkIds = json_decode($networkIds, true) ?? [];
                            }
                            $networks = !empty($networkIds)
                                ? AdNetwork::whereIn('id', (array) $networkIds)->orderBy('name')->pluck('name')->implode(', ')
                                : null;
                            $parts = array_filter([$networks, $state['format'] ?? null]);
                            return $parts ? implode(' — ', $parts) : 'Line item mới';
                        }),

                    // ── Footer: tổng ngân sách kế hoạch ──────────────────────
                    Forms\Components\Grid::make(4)
                        ->schema([
                            Forms\Components\Placeholder::make('budget_display')
                                ->label('Tổng ngân sách kế hoạch')
                                ->content(function (Forms\Get $get): HtmlString {
                                    $items  = $get('line_items') ?? [];
                                    $total  = collect($items)->sum(fn ($item) => (float) ($item['line_budget'] ?? 0));
                                    $formatted = $total > 0
                                        ? number_format($total, 0, ',', '.') . ' VND'
                                        : '—';
                                    return new HtmlString(
                                        '<span class="text-xl font-bold text-primary-600 dark:text-primary-400">'
                                        . e($formatted) .
                                        '</span>'
                                    );
                                })
                                ->columnSpan(4),
                        ])
                        ->columnSpanFull()
                        ->extraAttributes([
                            'class' => 'border-t border-gray-200 dark:border-gray-700 pt-4 mt-2',
                        ]),
                ]),
        ]);
    }

    // ─── Form submission ──────────────────────────────────────────────────────

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Lift line_items out before Plan is created
        $this->lineItemsData = $data['line_items'] ?? [];
        unset($data['line_items']);

        $data['adops_id'] = auth()->id();
        $data['status']   = 'draft';

        return $data;
    }

    protected function afterCreate(): void
    {
        foreach ($this->lineItemsData as $index => $item) {
            PlanLineItem::create([
                'plan_id'            => $this->record->id,
                'brief_line_item_id' => $item['brief_line_item_id'] ?? null,
                'created_by'         => $item['created_by'] ?? auth()->id(),
                'source'             => $item['source'] ?? 'adops',
                'format'             => $item['format'] ?? null,
                'targeting'          => $item['targeting'] ?? null,
                'start_date'         => $item['start_date'] ?? null,
                'end_date'           => $item['end_date'] ?? null,
                'live_days'          => isset($item['live_days']) ? (int) $item['live_days'] : null,
                'unit'               => $item['unit'] ?? null,
                'guaranteed_units'   => isset($item['guaranteed_units']) ? (float) $item['guaranteed_units'] : null,
                'unit_cost'          => isset($item['unit_cost']) ? (float) $item['unit_cost'] : null,
                'daily_spots'        => isset($item['daily_spots']) ? (int) $item['daily_spots'] : null,
                'est_impression'     => isset($item['est_impression']) ? (int) $item['est_impression'] : null,
                'est_impression_day' => isset($item['est_impression_day']) ? (int) $item['est_impression_day'] : null,
                'est_ad_spot'        => isset($item['est_ad_spot']) ? (int) $item['est_ad_spot'] : null,
                'status'             => 'pending',
                'notes'              => $item['notes'] ?? null,
                'sort_order'         => $index,
            ]);
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

    // ─── Redirect after creation ──────────────────────────────────────────────

    protected function getRedirectUrl(): string
    {
        return PlanResource::getUrl('view', ['record' => $this->record]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private static function recalcLineItem(Forms\Get $get, Forms\Set $set): void
    {
        $unit       = $get('unit') ?? 'cpm';
        $guaranteed = (int) ($get('guaranteed_units') ?? 0);
        $unitCost   = (float) ($get('unit_cost') ?? 0);

        // live_days from date range
        $start    = $get('start_date');
        $end      = $get('end_date');
        $liveDays = 0;
        if ($start && $end) {
            $liveDays = max(0, Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1);
            $set('live_days', $liveDays);
        }

        // line_budget
        $budget = match ($unit) {
            'cpm'   => $guaranteed * $unitCost,
            'cpd'   => $guaranteed * $unitCost * $liveDays,
            'io'    => $guaranteed * $liveDays * $unitCost,
            default => 0,
        };
        $set('line_budget', round($budget, 2));
    }
}
