<?php

namespace App\Filament\Resources\ContractResource\Pages;

use App\Filament\Resources\ContractResource;
use App\Models\Booking;
use Filament\Resources\Pages\CreateRecord;

class CreateContract extends CreateRecord
{
    protected static string $resource = ContractResource::class;

    // Pre-fill form data từ Booking khi redirect đến đây
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $bookingId = request()->query('booking_id');
        if ($bookingId && $booking = Booking::find($bookingId)) {
            $data['booking_id']             = $booking->id;
            $data['customer_id']            = $booking->customer_id;
            $data['name']                   = $booking->campaign_name;
            $data['start_date']             = $booking->start_date;
            $data['end_date']               = $booking->end_date;
            $data['total_value_estimated']  = $booking->total_budget;
            $data['contract_type']          = 'ads';
            $data['sale_owner_id']          = $booking->sale_id;
        }
        return $data;
    }

    protected function afterCreate(): void
    {
        // Sau khi tạo Contract, link ngược lại Booking
        $bookingId = request()->query('booking_id');
        if ($bookingId && $booking = Booking::find($bookingId)) {
            $booking->update([
                'contract_id' => $this->getRecord()->id,
                'status'      => 'contract_signed',
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
