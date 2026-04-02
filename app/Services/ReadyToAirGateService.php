<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\ReadyToAirGate;
use Illuminate\Support\Facades\Log;

class ReadyToAirGateService
{
    /**
     * Re-evaluate all gate conditions for a booking and update status if fully met.
     */
    public function evaluate(Booking $booking): void
    {
        $gate = $booking->readyToAirGate;
        if (! $gate) {
            return;
        }

        // Re-derive conditions from actual data
        $contractSigned   = $booking->contract?->status === 'signed';
        $fullyBought      = $booking->checkFullyBought();
        $creativeApproved = $booking->creativeSubmissions()
            ->where('status', 'approved')->exists();
        $trafficked = $booking->campaignTraffics()
            ->where('status', '!=', 'cancelled')
            ->whereNotIn('status', ['draft'])
            ->exists();
        $qaPassed = $booking->campaignTraffics()
            ->whereIn('status', ['approved', 'live', 'completed'])
            ->exists();
        $paymentReceived = $booking->contract
            ? $booking->contract->paymentSchedules()
                  ->where('status', 'paid')
                  ->where('schedule_type', 'advance')
                  ->exists()
            : false;

        $gate->update([
            'booking_fully_bought' => $fullyBought,
            'contract_signed'      => $contractSigned,
            'creative_approved'    => $creativeApproved,
            'campaign_trafficked'  => $trafficked,
            'qa_passed'            => $qaPassed,
            'payment_received'     => $paymentReceived,
        ]);

        $allMet = $gate->fresh()->evaluateAll();

        if ($allMet && $booking->status !== 'ready_to_air' && $booking->status !== 'live') {
            $booking->update(['status' => 'ready_to_air']);

            Log::info('Booking cleared Ready-to-Air gate', ['booking_id' => $booking->id]);

            // Notify sale + adops
            $this->notifyReadyToAir($booking);
        }
    }

    /**
     * Manually update a single gate condition.
     */
    public function updateCondition(Booking $booking, string $condition, bool $value): void
    {
        $gate = $booking->readyToAirGate;
        if (! $gate) {
            return;
        }
        $gate->updateCondition($condition, $value);

        // Re-check if all met after manual update
        $this->evaluate($booking);
    }

    /**
     * Create a fresh gate record when a booking is created.
     */
    public function createGate(Booking $booking): ReadyToAirGate
    {
        return ReadyToAirGate::create([
            'booking_id' => $booking->id,
        ]);
    }

    private function notifyReadyToAir(Booking $booking): void
    {
        $notifiable = collect([$booking->sale, $booking->adops])->filter();

        foreach ($notifiable as $user) {
            \Filament\Notifications\Notification::make()
                ->title('Booking sẵn sàng phát sóng!')
                ->body("{$booking->booking_no} — {$booking->campaign_name} đã đáp ứng đủ điều kiện phát sóng.")
                ->icon('heroicon-o-signal')
                ->iconColor('success')
                ->sendToDatabase($user);
        }
    }
}
