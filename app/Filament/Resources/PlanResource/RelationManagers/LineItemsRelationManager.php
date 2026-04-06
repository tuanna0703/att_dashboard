<?php

namespace App\Filament\Resources\PlanResource\RelationManagers;

use App\Filament\Forms\LineItemSchema;
use App\Models\AdNetwork;
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

        return $form->schema(array_merge([
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
        ], LineItemSchema::schema(withNotes: true)));
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

}
