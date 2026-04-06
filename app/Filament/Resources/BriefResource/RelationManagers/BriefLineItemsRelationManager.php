<?php

namespace App\Filament\Resources\BriefResource\RelationManagers;

use App\Models\AdNetwork;
use App\Models\BriefLineItem;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\Summarizers\Sum;
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

    // ─── Table ────────────────────────────────────────────────────────────────

    public function table(Table $table): Table
    {
        return $table
            ->reorderable(false)
            ->paginated(false)
            ->columns([
                TextColumn::make('targeting')
                    ->label('Network / Format')
                    ->getStateUsing(function ($record) {
                        $ids = $record->targeting ?? [];
                        if (empty($ids)) return '—';
                        return AdNetwork::whereIn('id', (array) $ids)->orderBy('name')->pluck('name')->implode(', ');
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
                    ->label('NET')
                    ->money(fn ($record) => $record->brief?->currency ?? 'VND')
                    ->weight('bold')
                    ->alignEnd()
                    ->summarize(Sum::make()->label('Tổng NET')
                        ->money(fn () => $this->getOwnerRecord()->currency ?? 'VND')),

                TextColumn::make('gross_amount')
                    ->label('GROSS')
                    ->money(fn ($record) => $record->brief?->currency ?? 'VND')
                    ->alignEnd()
                    ->color('success')
                    ->summarize(Sum::make()->label('Tổng GROSS')
                        ->money(fn () => $this->getOwnerRecord()->currency ?? 'VND')),

                TextColumn::make('est_impression')
                    ->label('Impression')
                    ->numeric()
                    ->alignEnd()
                    ->placeholder('—')
                    ->summarize(Sum::make()->label('Tổng')->numeric()),
            ])
            ->actions([
                Tables\Actions\Action::make('view_kpi')
                    ->label('')
                    ->tooltip('Xem KPI')
                    ->icon('heroicon-o-chart-bar')
                    ->color('gray')
                    ->modalHeading('Est KPI')
                    ->modalContent(fn ($record) => view('filament.modals.kpi-detail', compact('record')))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Đóng'),
            ]);
    }
}
