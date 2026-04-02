<?php

namespace App\Filament\Resources\BriefResource\RelationManagers;

use App\Models\AdNetwork;
use App\Models\BriefLineItem;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BriefLineItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'briefLineItems';
    protected static ?string $title = 'Line Items';

    public function isReadOnly(): bool
    {
        return true;
    }

    // ─── Infolist for KPI modal (used by ViewAction) ──────────────────────────

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make()->schema([
                TextEntry::make('targeting')
                    ->label('Network')
                    ->formatStateUsing(function ($state) {
                        if (empty($state)) return '—';
                        $ids = is_array($state) ? $state : (json_decode($state, true) ?? []);
                        return AdNetwork::whereIn('id', $ids)->orderBy('name')->pluck('name')->implode(', ');
                    }),

                TextEntry::make('format')->label('Format')->placeholder('—'),

                TextEntry::make('est_impression')
                    ->label('Est Impression')
                    ->formatStateUsing(fn ($state) => $state ? number_format((int) $state, 0, ',', '.') : '—'),

                TextEntry::make('est_impression_day')
                    ->label('Est Impression/Day')
                    ->formatStateUsing(fn ($state) => $state ? number_format((int) $state, 0, ',', '.') : '—'),

                TextEntry::make('est_ad_spot')
                    ->label('Est Ad Spot')
                    ->formatStateUsing(fn ($state) => $state ? number_format((int) $state, 0, ',', '.') : '—'),
            ])->columns(3),
        ]);
    }

    // ─── Table ────────────────────────────────────────────────────────────────

    public function table(Table $table): Table
    {
        return $table
            ->reorderable(false)
            ->paginated(false)
            ->columns([
                TextColumn::make('targeting')
                    ->label('Network / Format')
                    ->formatStateUsing(function ($state) {
                        if (empty($state)) return '—';
                        $ids = is_array($state) ? $state : (json_decode($state, true) ?? []);
                        return AdNetwork::whereIn('id', $ids)->orderBy('name')->pluck('name')->implode(', ');
                    })
                    ->description(fn ($record) => $record->format ?? '')
                    ->wrap(),

                TextColumn::make('start_date')
                    ->label('Ngày chạy')
                    ->getStateUsing(function ($record) {
                        $start = $record->start_date?->format('d/m/Y') ?? '—';
                        $end   = $record->end_date?->format('d/m/Y') ?? '—';
                        return "{$start} → {$end}";
                    }),

                TextColumn::make('live_days')
                    ->label('Live Days')
                    ->suffix(' ngày')
                    ->placeholder('—')
                    ->alignCenter(),

                TextColumn::make('unit')
                    ->label('Unit')
                    ->badge()
                    ->formatStateUsing(fn ($state) => BriefLineItem::$units[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'cpm'   => 'info',
                        'cpd'   => 'warning',
                        'io'    => 'success',
                        default => 'gray',
                    })
                    ->alignCenter(),

                TextColumn::make('guaranteed_units')
                    ->label('Guaranteed Units')
                    ->formatStateUsing(fn ($state) => $state ? number_format((int) $state, 0, ',', '.') : '—')
                    ->alignEnd(),

                TextColumn::make('unit_cost')
                    ->label('Unit Cost')
                    ->formatStateUsing(fn ($state) => $state ? number_format((float) $state, 0, ',', '.') : '—')
                    ->alignEnd(),

                TextColumn::make('line_budget')
                    ->label('Budget')
                    ->formatStateUsing(fn ($state, $record) => $state
                        ? number_format((float) $state, 0, ',', '.') . ' ' . ($record->brief?->currency ?? 'VND')
                        : '—')
                    ->weight('bold')
                    ->alignEnd(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('')
                    ->tooltip('Xem KPI')
                    ->icon('heroicon-o-chart-bar')
                    ->color('gray')
                    ->modalHeading('Est KPI'),
            ]);
    }
}
