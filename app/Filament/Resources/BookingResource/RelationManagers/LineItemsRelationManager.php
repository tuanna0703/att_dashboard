<?php

namespace App\Filament\Resources\BookingResource\RelationManagers;

use App\Models\AdNetwork;
use App\Models\BookingLineItem;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
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
                // ── Network / Format ──────────────────────────────────────────
                Tables\Columns\TextColumn::make('format')
                    ->label('Network / Format')
                    ->html()
                    ->getStateUsing(fn (BookingLineItem $record) => (function () use ($record) {
                        $names = $record->targeting_names ?? [];
                        $networkStr = implode(', ', $names);
                        $top = $networkStr
                            ? '<div class="font-semibold text-gray-950 dark:text-white" style="white-space:normal;word-break:break-word;">' . e($networkStr) . '</div>'
                            : '';
                        $bottom = '<div class="text-sm text-gray-500 dark:text-gray-400">' . e($record->format) . '</div>';
                        return '<div style="max-width:320px;min-width:0;">' . $top . $bottom . '</div>';
                    })())
                    ->searchable(false)
                    ->wrap(),

                // ── Date range ────────────────────────────────────────────────
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Thời gian')
                    ->html()
                    ->getStateUsing(fn (BookingLineItem $record) =>
                        '<div class="tabular-nums text-sm">' . ($record->start_date?->format('d/m/Y') ?? '—') . '</div>'
                        . '<div class="text-gray-400 dark:text-gray-500 text-xs text-center leading-none py-0.5">↓</div>'
                        . '<div class="tabular-nums text-sm">' . ($record->end_date?->format('d/m/Y') ?? '—') . '</div>'
                    ),

                // ── KPI ───────────────────────────────────────────────────────
                Tables\Columns\TextColumn::make('guaranteed_units')
                    ->label('KPI')
                    ->formatStateUsing(fn ($state, BookingLineItem $record) =>
                        number_format((float) $state, 0, ',', '.') . ' ' . strtoupper($record->unit)
                    )
                    ->alignEnd(),

                // ── Ngân sách ─────────────────────────────────────────────────
                Tables\Columns\TextColumn::make('line_budget')
                    ->label('Ngân sách')
                    ->money(fn (BookingLineItem $record) => $record->booking?->currency ?? 'VND')
                    ->alignEnd()
                    ->weight('bold'),

                // ── Trạng thái mua ────────────────────────────────────────────
                Tables\Columns\TextColumn::make('buying_status')
                    ->label('Trạng thái mua')
                    ->badge()
                    ->formatStateUsing(fn ($state) => BookingLineItem::$buyingStatuses[$state] ?? $state)
                    ->color(fn ($state) => BookingLineItem::$buyingStatusColors[$state] ?? 'gray'),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
