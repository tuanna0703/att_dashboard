<?php

namespace App\Filament\Resources\BookingResource\RelationManagers;

use App\Models\BookingLineItem;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Table;

class LineItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'lineItems';
    protected static ?string $title       = 'Line Items';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('format')
            ->reorderable(false)
            ->defaultSort('sort_order')
            ->columns([
                // ── Network / Format / City ──────────────────────────────────
                Tables\Columns\TextColumn::make('format')
                    ->label('Network / Format')
                    ->html()
                    ->getStateUsing(fn (BookingLineItem $record) => (function () use ($record) {
                        $names = $record->targeting_names ?? [];
                        $networkStr = implode(', ', $names);
                        $top = $networkStr
                            ? '<div class="font-semibold text-gray-950 dark:text-white" style="white-space:normal;word-break:break-word;">' . e($networkStr) . '</div>'
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
                    ->placeholder('—')
                    ->summarize(Sum::make()->label('Tổng')),

                // ── Weeks ───────────────────────────────────────────────────
                Tables\Columns\TextColumn::make('total_weeks')
                    ->label('Tuần')
                    ->html()
                    ->getStateUsing(fn (BookingLineItem $record) =>
                        ($record->buy_weeks ?? 0) . '<span class="text-xs text-gray-400"> +' . ($record->foc_weeks ?? 0) . ' FOC</span>'
                    )
                    ->alignCenter(),

                // ── Date range ──────────────────────────────────────────────
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Thời gian')
                    ->html()
                    ->getStateUsing(fn (BookingLineItem $record) =>
                        '<span class="tabular-nums text-sm">' . ($record->start_date?->format('d/m/Y') ?? '—') . '</span>'
                        . '<span class="text-gray-400 text-xs mx-1">→</span>'
                        . '<span class="tabular-nums text-sm">' . ($record->end_date?->format('d/m/Y') ?? '—') . '</span>'
                    ),

                // ── NET ─────────────────────────────────────────────────────
                Tables\Columns\TextColumn::make('line_budget')
                    ->label('NET')
                    ->money(fn (BookingLineItem $record) => $record->booking?->currency ?? 'VND')
                    ->alignEnd()
                    ->weight('bold')
                    ->summarize(Sum::make()->label('Tổng NET')
                        ->money(fn () => $this->getOwnerRecord()->currency ?? 'VND')),

                // ── GROSS ───────────────────────────────────────────────────
                Tables\Columns\TextColumn::make('gross_amount')
                    ->label('GROSS')
                    ->money(fn (BookingLineItem $record) => $record->booking?->currency ?? 'VND')
                    ->alignEnd()
                    ->color('success')
                    ->summarize(Sum::make()->label('Tổng GROSS')
                        ->money(fn () => $this->getOwnerRecord()->currency ?? 'VND')),

                // ── Impression ──────────────────────────────────────────────
                Tables\Columns\TextColumn::make('est_impression')
                    ->label('Impression')
                    ->numeric()
                    ->alignEnd()
                    ->placeholder('—')
                    ->summarize(Sum::make()->label('Tổng')->numeric()),

                // ── Trạng thái mua ──────────────────────────────────────────
                Tables\Columns\TextColumn::make('buying_status')
                    ->label('TT Mua')
                    ->badge()
                    ->formatStateUsing(fn ($state) => BookingLineItem::$buyingStatuses[$state] ?? $state)
                    ->color(fn ($state) => BookingLineItem::$buyingStatusColors[$state] ?? 'gray'),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
