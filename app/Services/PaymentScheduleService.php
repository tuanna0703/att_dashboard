<?php

namespace App\Services;

use App\Models\PaymentSchedule;
use App\Models\Receipt;
use App\Models\ReceiptAllocation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentScheduleService
{
    /**
     * Daily job: mark pending/invoiced/partially_paid schedules as overdue
     * if due_date < today.
     *
     * Returns count of records updated.
     */
    public function markOverdue(): int
    {
        $count = PaymentSchedule::query()
            ->whereIn('status', ['pending', 'invoiced', 'partially_paid'])
            ->whereDate('due_date', '<', Carbon::today())
            ->update(['status' => 'overdue']);

        Log::info("[PaymentScheduleService] markOverdue: {$count} schedules marked overdue.");

        return $count;
    }

    /**
     * Sync a single schedule's status based on how much has been collected.
     *
     * Rules:
     *   received == 0              → pending (or invoiced if invoice linked)
     *   0 < received < total       → partially_paid
     *   received >= total          → paid
     *   due_date past + not paid   → overdue (only if not already paid)
     */
    public function syncStatus(PaymentSchedule $schedule): void
    {
        $total    = (float) ($schedule->amount + $schedule->vat_amount);
        $received = (float) $schedule->receiptAllocations()->sum('allocated_amount');

        if ($received >= $total && $total > 0) {
            $schedule->status = 'paid';
        } elseif ($received > 0) {
            $schedule->status = 'partially_paid';
        } elseif ($schedule->invoice_id !== null) {
            $schedule->status = 'invoiced';
        } elseif ($schedule->due_date !== null && $schedule->due_date->isPast()) {
            $schedule->status = 'overdue';
        } else {
            $schedule->status = 'pending';
        }

        $schedule->save();
    }

    /**
     * Allocate a receipt to one or more payment schedules.
     *
     * $allocations = [
     *   ['payment_schedule_id' => 1, 'allocated_amount' => 5000000],
     *   ['payment_schedule_id' => 2, 'allocated_amount' => 3000000],
     * ]
     *
     * Validates:
     *   - Total allocated cannot exceed receipt amount
     *   - Each schedule must not be already paid
     *
     * @throws \InvalidArgumentException
     */
    public function allocateReceipt(Receipt $receipt, array $allocations): void
    {
        $totalAllocating = collect($allocations)->sum('allocated_amount');
        $alreadyAllocated = (float) $receipt->allocations()->sum('allocated_amount');

        if ($totalAllocating + $alreadyAllocated > (float) $receipt->amount) {
            throw new \InvalidArgumentException(
                "Tổng phân bổ ({$totalAllocating}) vượt quá số tiền phiếu thu còn lại (" .
                ($receipt->amount - $alreadyAllocated) . ').'
            );
        }

        DB::transaction(function () use ($receipt, $allocations) {
            foreach ($allocations as $item) {
                $schedule = PaymentSchedule::findOrFail($item['payment_schedule_id']);

                if ($schedule->status === 'paid') {
                    throw new \InvalidArgumentException(
                        "Đợt thanh toán #{$schedule->id} đã được thanh toán đủ, không thể phân bổ thêm."
                    );
                }

                ReceiptAllocation::updateOrCreate(
                    [
                        'receipt_id'          => $receipt->id,
                        'payment_schedule_id' => $schedule->id,
                    ],
                    [
                        'allocated_amount' => $item['allocated_amount'],
                    ]
                );

                $this->syncStatus($schedule);
            }
        });
    }

    /**
     * Remove an allocation and re-sync the affected schedule's status.
     */
    public function removeAllocation(ReceiptAllocation $allocation): void
    {
        $schedule = $allocation->paymentSchedule;
        $allocation->delete();
        $this->syncStatus($schedule);
    }

    /**
     * Mark a schedule as invoiced when an invoice is linked.
     */
    public function markInvoiced(PaymentSchedule $schedule): void
    {
        if (in_array($schedule->status, ['paid', 'partially_paid'])) {
            return;
        }

        $schedule->status = 'invoiced';
        $schedule->save();
    }
}
