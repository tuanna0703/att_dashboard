<?php

namespace App\Filament\Resources\MediaBuyingOrderResource\Pages;

use App\Filament\Resources\MediaBuyingOrderResource;
use App\Models\MediaBuyingOrder;
use App\Models\User;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewMediaBuyingOrder extends ViewRecord
{
    protected static string $resource = MediaBuyingOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn () => $this->record->status === 'draft'),

            Actions\Action::make('submit_dept')
                ->label('Gửi TP duyệt')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->visible(fn () => $this->record->status === 'draft')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update(['status' => 'pending_dept']);
                    Notification::make()->title('Đã gửi MBO cho Trưởng phòng duyệt')->success()->send();
                    $this->refreshFormData(['status']);
                }),

            Actions\Action::make('dept_approve')
                ->label('TP Duyệt')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->record->status === 'pending_dept'
                    && auth()->user()->hasAnyRole(['coo', 'vice_ceo', 'ceo']))
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update([
                        'status'                => 'dept_approved',
                        'dept_head_id'          => auth()->id(),
                        'dept_head_approved_at' => now(),
                    ]);
                    Notification::make()->title('Trưởng phòng đã duyệt')->success()->send();
                    $this->refreshFormData(['status', 'dept_head_id', 'dept_head_approved_at']);
                }),

            Actions\Action::make('dept_reject')
                ->label('TP Từ chối')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => $this->record->status === 'pending_dept'
                    && auth()->user()->hasAnyRole(['coo', 'vice_ceo', 'ceo']))
                ->form([
                    Forms\Components\Textarea::make('rejection_reason')
                        ->label('Lý do từ chối')->required()->rows(3),
                ])
                ->action(function (array $data) {
                    $this->record->update(['status' => 'rejected', 'rejection_reason' => $data['rejection_reason']]);
                    Notification::make()->title('Đã từ chối MBO')->danger()->send();
                    $this->refreshFormData(['status', 'rejection_reason']);
                }),

            Actions\Action::make('submit_finance')
                ->label('Gửi kết toán')
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->visible(fn () => $this->record->status === 'dept_approved')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update(['status' => 'pending_finance']);
                    Notification::make()->title('Đã gửi MBO cho kết toán')->success()->send();
                    $this->refreshFormData(['status']);
                }),

            Actions\Action::make('finance_approve')
                ->label('Kết toán Duyệt')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->record->status === 'pending_finance'
                    && auth()->user()->hasAnyRole(['finance_manager', 'ceo']))
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update([
                        'status'              => 'finance_approved',
                        'finance_approved_by' => auth()->id(),
                        'finance_approved_at' => now(),
                    ]);
                    Notification::make()->title('Kết toán đã duyệt MBO')->success()->send();
                    $this->refreshFormData(['status', 'finance_approved_by', 'finance_approved_at']);
                }),

            Actions\Action::make('finance_reject')
                ->label('Kết toán Từ chối')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => $this->record->status === 'pending_finance'
                    && auth()->user()->hasAnyRole(['finance_manager', 'ceo']))
                ->form([
                    Forms\Components\Textarea::make('rejection_reason')
                        ->label('Lý do từ chối')->required()->rows(3),
                ])
                ->action(function (array $data) {
                    $this->record->update(['status' => 'rejected', 'rejection_reason' => $data['rejection_reason']]);
                    Notification::make()->title('Kết toán đã từ chối MBO')->danger()->send();
                    $this->refreshFormData(['status', 'rejection_reason']);
                }),

            Actions\Action::make('send_to_buyer')
                ->label('Gửi Buyer')
                ->icon('heroicon-o-arrow-right-circle')
                ->color('primary')
                ->visible(fn () => $this->record->status === 'finance_approved')
                ->form([
                    Forms\Components\Select::make('buyer_id')
                        ->label('Chọn Buyer')
                        ->options(User::orderBy('name')->pluck('name', 'id'))
                        ->required()
                        ->searchable(),
                ])
                ->modalHeading('Gửi MBO cho Buyer thực hiện')
                ->action(function (array $data) {
                    $this->record->update([
                        'status'   => 'sent_to_buyer',
                        'buyer_id' => $data['buyer_id'],
                    ]);
                    Notification::make()->title('Đã gửi MBO cho Buyer')->success()->send();
                    $this->refreshFormData(['status', 'buyer_id']);
                }),

            Actions\Action::make('mark_executed')
                ->label('Đã thực hiện mua')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn () => $this->record->status === 'sent_to_buyer'
                    && (auth()->user()->hasRole('media_buyer') || auth()->user()->hasRole('ceo')))
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update([
                        'status'            => 'executed',
                        'buyer_executed_at' => now(),
                    ]);
                    Notification::make()->title('Đã xác nhận thực hiện mua')->success()->send();
                    $this->refreshFormData(['status', 'buyer_executed_at']);
                }),

            Actions\Action::make('mark_completed')
                ->label('Hoàn thành')
                ->icon('heroicon-o-archive-box')
                ->color('gray')
                ->visible(fn () => $this->record->status === 'executed')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update(['status' => 'completed']);
                    Notification::make()->title('MBO đã hoàn thành')->success()->send();
                    $this->refreshFormData(['status']);
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Thông tin MBO')->schema([
                TextEntry::make('order_no')->label('Mã MBO')->weight('bold')->copyable(),
                TextEntry::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn ($state) => MediaBuyingOrder::$statuses[$state] ?? $state)
                    ->color(fn ($state) => MediaBuyingOrder::$statusColors[$state] ?? 'gray'),
                TextEntry::make('contract.contract_code')->label('Hợp đồng'),
                TextEntry::make('booking.booking_no')->label('Booking')->placeholder('—'),
                TextEntry::make('total_amount')->label('Tổng tiền')->money('VND')->weight('bold'),
                TextEntry::make('createdBy.name')->label('AdOps tạo'),
                TextEntry::make('note')->label('Ghi chú')->placeholder('—')->columnSpanFull(),
                TextEntry::make('rejection_reason')->label('Lý do từ chối')->placeholder('—')->columnSpanFull(),
            ])->columns(3),

            Section::make('Quá trình duyệt')->schema([
                TextEntry::make('deptHead.name')->label('Trưởng phòng duyệt')->placeholder('Chờ duyệt'),
                TextEntry::make('dept_head_approved_at')->label('Ngày TP duyệt')->dateTime('d/m/Y H:i')->placeholder('—'),
                TextEntry::make('financeApprovedBy.name')->label('Kết toán duyệt')->placeholder('Chờ duyệt'),
                TextEntry::make('finance_approved_at')->label('Ngày KT duyệt')->dateTime('d/m/Y H:i')->placeholder('—'),
                TextEntry::make('buyer.name')->label('Buyer thực hiện')->placeholder('—'),
                TextEntry::make('buyer_executed_at')->label('Ngày thực hiện')->dateTime('d/m/Y H:i')->placeholder('—'),
            ])->columns(3),

            Section::make('Chi tiết inventory')->schema([
                RepeatableEntry::make('items')
                    ->label('')
                    ->schema([
                        TextEntry::make('adNetwork.name')->label('Mạng lưới'),
                        TextEntry::make('description')->label('Mô tả')->placeholder('—'),
                        TextEntry::make('screen_count')->label('Màn hình')->suffix(' màn'),
                        TextEntry::make('days')->label('Số ngày')->suffix(' ngày'),
                        TextEntry::make('unit_price')->label('Đơn giá/ngày')->money('VND'),
                        TextEntry::make('total_price')->label('Thành tiền')->money('VND')->weight('bold'),
                    ])
                    ->columns(6),
            ]),
        ]);
    }
}
