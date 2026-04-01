<?php

namespace App\Filament\Resources\BookingResource\Pages;

use App\Filament\Resources\BookingResource;
use App\Filament\Resources\ContractResource;
use App\Models\Booking;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewBooking extends ViewRecord
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn () => ! in_array($this->record->status, ['closed', 'cancelled'])),

            Actions\Action::make('create_contract')
                ->label('Tạo hợp đồng')
                ->icon('heroicon-o-document-plus')
                ->color('primary')
                ->visible(fn () => is_null($this->record->contract_id))
                ->url(fn () => ContractResource::getUrl('create', [
                    'booking_id'    => $this->record->id,
                    'customer_id'   => $this->record->customer_id,
                    'campaign_name' => $this->record->campaign_name,
                    'start_date'    => $this->record->start_date?->format('Y-m-d'),
                    'end_date'      => $this->record->end_date?->format('Y-m-d'),
                    'total_value'   => $this->record->total_budget,
                ])),

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
        ];
    }
}
