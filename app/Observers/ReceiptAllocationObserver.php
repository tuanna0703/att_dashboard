<?php

namespace App\Observers;

use App\Models\ReceiptAllocation;
use App\Services\PaymentScheduleService;

class ReceiptAllocationObserver
{
    public function __construct(private PaymentScheduleService $service) {}

    public function created(ReceiptAllocation $allocation): void
    {
        $this->service->syncStatus($allocation->paymentSchedule);
        $this->syncReceiptAmount($allocation);
    }

    public function updated(ReceiptAllocation $allocation): void
    {
        $this->service->syncStatus($allocation->paymentSchedule);
        $this->syncReceiptAmount($allocation);
    }

    public function deleted(ReceiptAllocation $allocation): void
    {
        $this->service->syncStatus($allocation->paymentSchedule);
        $this->syncReceiptAmount($allocation);
    }

    private function syncReceiptAmount(ReceiptAllocation $allocation): void
    {
        $receipt = $allocation->receipt;
        if (! $receipt) {
            return;
        }
        $total = (float) $receipt->allocations()->sum('allocated_amount');
        $receipt->timestamps = false;
        $receipt->amount = $total;
        $receipt->save();
        $receipt->timestamps = true;
    }
}
