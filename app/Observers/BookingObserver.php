<?php

namespace App\Observers;

use App\Models\Booking;
use App\Services\ReadyToAirGateService;

class BookingObserver
{
    public function __construct(private ReadyToAirGateService $gateService) {}

    /** Auto-create a ReadyToAirGate record when a booking is created */
    public function created(Booking $booking): void
    {
        $this->gateService->createGate($booking);
    }

    /** Re-evaluate gate when contract is attached or status changes */
    public function updated(Booking $booking): void
    {
        if ($booking->wasChanged('contract_id') || $booking->wasChanged('status')) {
            $this->gateService->evaluate($booking);
        }
    }
}
