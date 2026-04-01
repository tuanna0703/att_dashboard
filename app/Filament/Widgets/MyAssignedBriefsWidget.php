<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\BriefResource;
use App\Models\Brief;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class MyAssignedBriefsWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    protected static bool $isDiscovered = false;
    protected static ?string $heading = 'Brief được assign cho tôi';
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Brief::query()
                    ->where('adops_id', auth()->id())
                    ->whereNotIn('status', ['converted', 'rejected'])
                    ->orderBy('updated_at', 'desc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('brief_no')
                    ->label('Mã Brief')
                    ->weight('bold')
                    ->url(fn (Brief $record) => BriefResource::getUrl('view', ['record' => $record])),

                Tables\Columns\TextColumn::make('campaign_name')
                    ->label('Campaign')
                    ->limit(40),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Khách hàng'),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Bắt đầu')
                    ->date('d/m/Y')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('revisions_count')
                    ->label('Rev.')
                    ->counts('revisions')
                    ->alignCenter(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Trạng thái')
                    ->formatStateUsing(fn ($state) => Brief::$statuses[$state] ?? $state)
                    ->colors(Brief::$statusColors),
            ])
            ->emptyStateHeading('Không có brief nào đang chờ xử lý')
            ->emptyStateIcon('heroicon-o-document-magnifying-glass')
            ->paginated(false);
    }
}
