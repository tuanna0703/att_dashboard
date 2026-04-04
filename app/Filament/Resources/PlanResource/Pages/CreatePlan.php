<?php

namespace App\Filament\Resources\PlanResource\Pages;

use App\Events\Plan\PlanCreated;
use App\Filament\Resources\PlanResource;
use App\Models\Brief;
use App\Models\BriefLineItem;
use App\Models\Plan;
use App\Models\PlanLineItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
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

        $brief        = Brief::with(['customer', 'sale', 'briefLineItems'])->findOrFail($this->briefId);
        $fromPlanId   = (int) request()->query('from_plan_id', 0);

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
                        'unit'               => $item->unit,
                        'guaranteed_units'   => $item->guaranteed_units,
                        'unit_cost'          => $item->unit_cost,
                        'line_budget'        => $item->line_budget,
                        'est_impression'     => $item->est_impression,
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
                    'unit'               => $item->unit,
                    'guaranteed_units'   => $item->guaranteed_units,
                    'unit_cost'          => $item->unit_cost,
                    'line_budget'        => $item->line_budget,
                    'est_impression'     => $item->est_impression,
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
            ? \Carbon\Carbon::parse($briefStart)->format('d/m/Y') . ' → ' . \Carbon\Carbon::parse($briefEnd)->format('d/m/Y')
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
                            // Row 1: Format + Targeting
                            Forms\Components\TextInput::make('format')
                                ->label('Format')
                                ->required()
                                ->placeholder('VD: Billboard, Lightbox, Video...')
                                ->columnSpan(2),

                            Forms\Components\TagsInput::make('targeting')
                                ->label('Targeting')
                                ->placeholder('Thêm target...')
                                ->columnSpan(2),

                            // Row 2: Dates + Unit + Impression
                            Forms\Components\DatePicker::make('start_date')
                                ->label('Từ ngày')
                                ->displayFormat('d/m/Y')
                                ->required(),

                            Forms\Components\DatePicker::make('end_date')
                                ->label('Đến ngày')
                                ->displayFormat('d/m/Y')
                                ->afterOrEqual('start_date')
                                ->required(),

                            Forms\Components\Select::make('unit')
                                ->label('Đơn vị tính')
                                ->options(BriefLineItem::$units)
                                ->required(),

                            Forms\Components\TextInput::make('est_impression')
                                ->label('Est. Impressions')
                                ->numeric()
                                ->nullable(),

                            // Row 3: KPI + Price + Budget
                            Forms\Components\TextInput::make('guaranteed_units')
                                ->label('KPI (Số lượng)')
                                ->numeric()
                                ->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) =>
                                    self::recalcBudget($get, $set)
                                ),

                            Forms\Components\TextInput::make('unit_cost')
                                ->label('Đơn giá (₫)')
                                ->numeric()
                                ->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) =>
                                    self::recalcBudget($get, $set)
                                ),

                            Forms\Components\TextInput::make('line_budget')
                                ->label('Tổng ngân sách')
                                ->prefix('₫')
                                ->disabled()
                                ->dehydrated(false)
                                ->helperText('= KPI × Đơn giá'),

                            Forms\Components\Textarea::make('notes')
                                ->label('Ghi chú')
                                ->rows(1)
                                ->nullable(),

                            // Hidden fields — carry ownership metadata
                            Forms\Components\Hidden::make('brief_line_item_id'),
                            Forms\Components\Hidden::make('source'),
                            Forms\Components\Hidden::make('created_by'),
                        ])
                        ->columns(4)
                        ->addActionLabel('+ Thêm line item')
                        ->cloneable()
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(fn (array $state) =>
                            ($state['format'] ?? 'Line item')
                            . (isset($state['start_date'])
                                ? ' · ' . \Carbon\Carbon::parse($state['start_date'])->format('d/m/Y')
                                  . ' → ' . \Carbon\Carbon::parse($state['end_date'] ?? $state['start_date'])->format('d/m/Y')
                                : '')
                        ),
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
                'unit'               => $item['unit'] ?? null,
                'guaranteed_units'   => isset($item['guaranteed_units']) ? (float) $item['guaranteed_units'] : null,
                'unit_cost'          => isset($item['unit_cost']) ? (float) $item['unit_cost'] : null,
                'est_impression'     => isset($item['est_impression']) ? (int) $item['est_impression'] : null,
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

    private static function recalcBudget(Forms\Get $get, Forms\Set $set): void
    {
        $units = (float) ($get('guaranteed_units') ?? 0);
        $cost  = (float) ($get('unit_cost') ?? 0);

        if ($units > 0 && $cost > 0) {
            $set('line_budget', number_format($units * $cost, 0, ',', '.'));
        }
    }
}
