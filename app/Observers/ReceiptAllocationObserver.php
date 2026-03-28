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
    }

    public function updated(ReceiptAllocation $allocation): void
    {
        $this->service->syncStatus($allocation->paymentSchedule);
    }

    public function deleted(ReceiptAllocation $allocation): void
    {
        $this->service->syncStatus($allocation->paymentSchedule);
    }
}
