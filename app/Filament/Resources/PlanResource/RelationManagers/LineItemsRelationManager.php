<?php

namespace App\Filament\Resources\PlanResource\RelationManagers;

use App\Models\BriefLineItem;
use App\Models\PlanLineItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
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
            Forms\Components\Section::make('Thông tin line item')->schema([
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

                Forms\Components\TextInput::make('format')
                    ->label('Format')
                    ->maxLength(100)
                    ->placeholder('VD: Billboard, Lightbox, Video...')
                    ->required(),

                Forms\Components\TagsInput::make('targeting')
                    ->label('Targeting')
                    ->placeholder('Thêm target...')
                    ->nullable(),

                Forms\Components\DatePicker::make('start_date')
                    ->label('Ngày bắt đầu')
                    ->displayFormat('d/m/Y')
                    ->required(),

                Forms\Components\DatePicker::make('end_date')
                    ->label('Ngày kết thúc')
                    ->displayFormat('d/m/Y')
                    ->after('start_date')
                    ->required(),
            ])->columns(2),

            Forms\Components\Section::make('KPI & Chi phí')->schema([
                Forms\Components\Select::make('unit')
                    ->label('Đơn vị tính')
                    ->options(BriefLineItem::$units)
                    ->required(),

                Forms\Components\TextInput::make('guaranteed_units')
                    ->label('KPI (Số lượng)')
                    ->numeric()
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) =>
                        self::recalcBudget($get, $set)
                    ),

                Forms\Components\TextInput::make('unit_cost')
                    ->label('Đơn giá')
                    ->numeric()
                    ->prefix('₫')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) =>
                        self::recalcBudget($get, $set)
                    ),

                Forms\Components\TextInput::make('line_budget')
                    ->label('Tổng ngân sách')
                    ->numeric()
                    ->prefix('₫')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Tự động: KPI × Đơn giá'),

                Forms\Components\TextInput::make('est_impression')
                    ->label('Est. Impressions')
                    ->numeric()
                    ->nullable(),
            ])->columns(3),

            Forms\Components\Section::make()->schema([
                Forms\Components\Textarea::make('notes')
                    ->label('Ghi chú')
                    ->rows(2)
                    ->columnSpanFull(),
            ]),
        ]);
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
                    ->description(fn ($record) => collect($record->targeting ?? [])->join(', '))
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
                    ->money('VND')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('line_budget')
                    ->label('Ngân sách')
                    ->money('VND')
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

    private static function recalcBudget(Forms\Get $get, Forms\Set $set): void
    {
        $units = (float) $get('guaranteed_units');
        $cost  = (float) $get('unit_cost');

        if ($units > 0 && $cost > 0) {
            $set('line_budget', number_format($units * $cost, 0, ',', '.'));
        }
    }
}
