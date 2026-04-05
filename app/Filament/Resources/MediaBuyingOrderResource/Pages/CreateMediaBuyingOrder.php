<?php

namespace App\Filament\Resources\MediaBuyingOrderResource\Pages;

use App\Filament\Resources\BookingResource;
use App\Filament\Resources\MediaBuyingOrderResource;
use App\Models\Booking;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateMediaBuyingOrder extends CreateRecord
{
    protected static string $resource = MediaBuyingOrderResource::class;

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Chọn Booking để tạo MBO')
                ->description('Hệ thống sẽ tự động tạo MBO với đầy đủ items từ line items của Booking.')
                ->schema([
                    Forms\Components\Select::make('booking_id')
                        ->label('Booking')
                        ->options(
                            Booking::whereNotNull('contract_id')
                                ->orderByDesc('id')
                                ->get()
                                ->mapWithKeys(fn (Booking $b) => [
                                    $b->id => "[{$b->booking_no}] {$b->campaign_name}",
                                ])
                        )
                        ->required()
                        ->searchable()
                        ->helperText('Chỉ hiển thị các Booking đã có hợp đồng.'),
                ]),
        ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $booking = Booking::findOrFail($data['booking_id']);

        return BookingResource::createMBOFromBooking($booking);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
