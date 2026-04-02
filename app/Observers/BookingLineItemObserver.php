<?php

namespace App\Observers;

use App\Models\BookingLineItem;
use App\Services\ReadyToAirGateService;

class BookingLineItemObserver
{
    public function __construct(private ReadyToAirGateService $gateService) {}

    /** Re-evaluate gate when buying_status changes */
    public function updated(BookingLineItem $item): void
    {
        if ($item->wasChanged('buying_status')) {
            $this->gateService->evaluate($item->booking);
        }
    }
}
