<?php

namespace App\Filament\Resources\Shared;

use App\Models\ActivityLog;
use App\Models\Brief;
use App\Models\Plan;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reusable relation manager that shows activity logs for either
 * a Brief (including all its Plans' logs) or a standalone Plan.
 *
 * Usage — add to getRelationManagers() on BriefResource and PlanResource.
 */
class ActivityLogRelationManager extends RelationManager
{
    // This manager does not map to an Eloquent relation directly;
    // we override getTableQuery() to build a custom query.
    protected static string $relationship = 'activityLogs';
    protected static ?string $title       = 'Lịch sử hoạt động';
    protected static ?string $icon        = 'heroicon-o-clock';

    public function isReadOnly(): bool
    {
        return true;
    }

    // ─── Custom query ─────────────────────────────────────────────────────────

    protected function getTableQuery(): Builder
    {
        $owner = $this->getOwnerRecord();

        $query = ActivityLog::query()->orderByDesc('created_at');

        if ($owner instanceof Brief) {
            $planIds = $owner->plans()->pluck('id');

            $query->where(function (Builder $q) use ($owner, $planIds) {
                $q->where(fn (Builder $q2) => $q2
                    ->where('subject_type', Brief::class)
                    ->where('subject_id', $owner->id)
                )->orWhere(fn (Builder $q2) => $q2
                    ->where('subject_type', Plan::class)
                    ->whereIn('subject_id', $planIds)
                );
            });
        } elseif ($owner instanceof Plan) {
            $query->where('subject_type', Plan::class)
                  ->where('subject_id', $owner->id);
        }

        return $query;
    }

    // ─── Table ────────────────────────────────────────────────────────────────

    public function table(Table $table): Table
    {
        return $table
            ->heading('Lịch sử hoạt động')
            ->recordTitleAttribute('description')
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            ->columns([

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Thời gian')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->width('150px'),

                Tables\Columns\BadgeColumn::make('event')
                    ->label('Hành động')
                    ->formatStateUsing(fn (string $state) => ActivityLog::$eventLabels[$state] ?? $state)
                    ->colors(ActivityLog::$eventColors)
                    ->width('160px'),

                Tables\Columns\TextColumn::make('causer_name')
                    ->label('Người thực hiện')
                    ->placeholder('—')
                    ->width('180px'),

                Tables\Columns\TextColumn::make('description')
                    ->label('Mô tả')
                    ->wrap(),

                Tables\Columns\TextColumn::make('properties.comment')
                    ->label('Ghi chú / Lý do')
                    ->placeholder('—')
                    ->limit(120)
                    ->wrap()
                    ->tooltip(fn ($record) => $record->properties['comment'] ?? null),

            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event')
                    ->label('Hành động')
                    ->options(ActivityLog::$eventLabels),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
