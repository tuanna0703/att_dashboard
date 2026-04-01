<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\MediaBuyingOrderResource;
use App\Models\MediaBuyingOrder;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class MyPendingMBOsWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    protected static bool $isDiscovered = false;
    protected static ?string $heading = 'Media Buying Orders cần xử lý';
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $userId = auth()->id();

        return $table
            ->query(
                MediaBuyingOrder::query()
                    ->where(function ($q) use ($userId) {
                        // Các MBO do tôi tạo còn đang chờ duyệt
                        $q->where('created_by', $userId)
                            ->whereIn('status', ['draft', 'pending_dept_head', 'dept_head_approved', 'pending_finance']);
                    })
                    ->orWhere(function ($q) use ($userId) {
                        // MBO assign cho tôi để execute
                        $q->where('buyer_id', $userId)
                            ->where('status', 'buyer_executing');
                    })
                    ->orderBy('updated_at', 'desc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('order_no')
                    ->label('Mã MBO')
                    ->weight('bold')
                    ->url(fn (MediaBuyingOrder $record) => MediaBuyingOrderResource::getUrl('view', ['record' => $record])),

                Tables\Columns\TextColumn::make('contract.contract_code')
                    ->label('Hợp đồng')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Tổng tiền')
                    ->money('VND'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Trạng thái')
                    ->formatStateUsing(fn ($state) => MediaBuyingOrder::$statuses[$state] ?? $state)
                    ->colors(MediaBuyingOrder::$statusColors),
            ])
            ->emptyStateHeading('Không có MBO nào cần xử lý')
            ->emptyStateIcon('heroicon-o-shopping-cart')
            ->paginated(false);
    }
}
