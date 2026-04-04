<?php

namespace App\Filament\Resources\BookingResource\Pages;

use App\Filament\Resources\BookingResource;
use App\Filament\Resources\BookingResource\RelationManagers\LineItemsRelationManager;
use App\Filament\Resources\BriefResource;
use App\Filament\Resources\ContractResource;
use App\Filament\Resources\PlanResource;
use App\Filament\Resources\Shared\ActivityLogRelationManager;
use App\Models\Booking;
use Filament\Actions;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\Tabs\Tab;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewBooking extends ViewRecord
{
    protected static string $resource = BookingResource::class;

    // ─── Relation managers ────────────────────────────────────────────────────

    public function getRelationManagers(): array
    {
        return [
            LineItemsRelationManager::class,
            ActivityLogRelationManager::class,
        ];
    }

    // ─── Title ────────────────────────────────────────────────────────────────

    public function getTitle(): string
    {
        return $this->record->booking_no . ' — ' . ($this->record->campaign_name ?? '');
    }

    // ─── Header actions ───────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\EditAction::make()
                    ->visible(fn () => ! in_array($this->record->status, ['closed', 'cancelled'])),

                Actions\Action::make('create_contract')
                    ->label('Tạo hợp đồng')
                    ->icon('heroicon-o-document-plus')
                    ->color('primary')
                    ->visible(fn () => is_null($this->record->contract_id))
                    ->requiresConfirmation()
                    ->modalHeading('Tạo hợp đồng từ Booking này?')
                    ->modalDescription('Hệ thống sẽ tạo hợp đồng quảng cáo với đầy đủ hạng mục từ line items của Booking.')
                    ->action(function () {
                        $contract = BookingResource::createContractFromBooking($this->record);
                        $this->redirect(ContractResource::getUrl('edit', ['record' => $contract]));
                    }),

                Actions\Action::make('mark_active')
                    ->label('Bắt đầu chạy')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn () => $this->record->status === 'contract_signed')
                    ->requiresConfirmation()
                    ->action(function () {
                        $this->record->update(['status' => 'campaign_active']);
                        Notification::make()->title('Campaign đang chạy')->success()->send();
                        $this->refreshFormData(['status']);
                    }),

                Actions\Action::make('mark_completed')
                    ->label('Đánh dấu đã chạy xong')
                    ->icon('heroicon-o-flag')
                    ->color('info')
                    ->visible(fn () => $this->record->status === 'campaign_active')
                    ->requiresConfirmation()
                    ->action(function () {
                        $this->record->update(['status' => 'campaign_completed']);
                        Notification::make()->title('Campaign đã kết thúc — chờ nghiệm thu')->success()->send();
                        $this->refreshFormData(['status']);
                    }),

                Actions\Action::make('mark_closed')
                    ->label('Đóng Booking')
                    ->icon('heroicon-o-archive-box')
                    ->color('gray')
                    ->visible(fn () => $this->record->status === 'acceptance_done')
                    ->requiresConfirmation()
                    ->action(function () {
                        $this->record->update(['status' => 'closed']);
                        Notification::make()->title('Booking đã được đóng')->success()->send();
                        $this->refreshFormData(['status']);
                    }),
            ]),
        ];
    }

    // ─── Infolist ─────────────────────────────────────────────────────────────

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([

            Tabs::make()->tabs([

                // ── Tab General ───────────────────────────────────────────────
                Tab::make('General')->schema([

                    // Mã Booking + Campaign gom 1 cột
                    TextEntry::make('booking_no')
                        ->label('Booking')
                        ->html()
                        ->getStateUsing(fn (Booking $record) =>
                            '<div class="font-semibold text-gray-950 dark:text-white">' . e($record->campaign_name) . '</div>'
                            . '<div class="text-xs text-gray-400 mt-0.5">' . e($record->booking_no) . '</div>'
                        ),

                    TextEntry::make('status')
                        ->label('Trạng thái')
                        ->badge()
                        ->formatStateUsing(fn ($state) => Booking::$statuses[$state] ?? $state)
                        ->color(fn ($state) => Booking::$statusColors[$state] ?? 'gray'),

                    TextEntry::make('customer.name')
                        ->label('Khách hàng')
                        ->placeholder('—'),

                    TextEntry::make('brief.brief_no')
                        ->label('Brief')
                        ->url(fn (Booking $record) => $record->brief_id
                            ? BriefResource::getUrl('view', ['record' => $record->brief_id])
                            : null
                        )
                        ->color('primary')
                        ->placeholder('—'),

                    TextEntry::make('plan.plan_no')
                        ->label('Plan được duyệt')
                        ->url(fn (Booking $record) => $record->plan_id
                            ? PlanResource::getUrl('view', ['record' => $record->plan_id])
                            : null
                        )
                        ->color('primary')
                        ->placeholder('—'),

                    TextEntry::make('total_budget')
                        ->label('Ngân sách')
                        ->money(fn (Booking $record) => $record->currency ?? 'VND')
                        ->weight('bold')
                        ->placeholder('—'),

                    // Bắt đầu → Kết thúc cùng 1 dòng
                    TextEntry::make('start_date')
                        ->label('Thời gian')
                        ->html()
                        ->getStateUsing(fn (Booking $record) =>
                            '<span class="tabular-nums text-sm">' . ($record->start_date?->format('d/m/Y') ?? '—') . '</span>'
                            . '<span class="text-gray-400 dark:text-gray-500 text-xs mx-1.5">→</span>'
                            . '<span class="tabular-nums text-sm">' . ($record->end_date?->format('d/m/Y') ?? '—') . '</span>'
                        ),

                ])->columns(3),

                // ── Tab Management ────────────────────────────────────────────
                Tab::make('Management')->schema([
                    TextEntry::make('sale.name')
                        ->label('Sale')
                        ->placeholder('—'),

                    TextEntry::make('adops.name')
                        ->label('AdOps')
                        ->placeholder('—'),

                    TextEntry::make('note')
                        ->label('Ghi chú')
                        ->placeholder('—')
                        ->columnSpanFull(),

                    TextEntry::make('contract.contract_code')
                        ->label('Hợp đồng')
                        ->badge()
                        ->color('success')
                        ->placeholder('Chưa có hợp đồng'),

                    TextEntry::make('grand_total')
                        ->label('Tổng thanh toán (sau thuế)')
                        ->money(fn (Booking $record) => $record->currency ?? 'VND')
                        ->weight('bold')
                        ->placeholder('—'),
                ])->columns(2),

            ])->columnSpanFull(),
        ]);
    }
}
