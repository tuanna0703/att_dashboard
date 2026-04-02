<?php

namespace App\Services;

use App\Models\BookingLineItem;
use App\Models\InventoryHold;
use App\Models\Screen;
use App\Models\ScreenAvailabilityLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryService
{
    /**
     * Check if a screen has available inventory for the requested period.
     *
     * @return array{available: bool, available_slots: int, conflict_count: int, note: string}
     */
    public function checkAvailability(
        int $screenId,
        Carbon $start,
        Carbon $end,
        int $spotsPerHour
    ): array {
        $screen = Screen::findOrFail($screenId);
        $totalSlotsPerHour = $screen->total_slots_per_hour;

        // Count active holds for each day in the range
        $days      = $start->diffInDays($end) + 1;
        $conflicts = InventoryHold::forScreen($screenId, $start->toDateString(), $end->toDateString())
            ->selectRaw('MAX(spots_per_hour) as max_held_per_hour')
            ->value('max_held_per_hour') ?? 0;

        $availableSlots = $totalSlotsPerHour - $conflicts;
        $available      = $availableSlots >= $spotsPerHour;

        return [
            'available'       => $available,
            'available_slots' => max(0, (int) $availableSlots),
            'total_slots'     => $totalSlotsPerHour,
            'held_slots'      => (int) $conflicts,
            'days'            => $days,
            'note'            => $available
                ? "Còn {$availableSlots}/{$totalSlotsPerHour} slot/giờ"
                : "Không đủ inventory: yêu cầu {$spotsPerHour}, còn {$availableSlots}/{$totalSlotsPerHour} slot/giờ",
        ];
    }

    /**
     * Create a soft hold on inventory for a booking line item.
     * Soft holds expire after 24 hours if not converted.
     */
    public function createSoftHold(BookingLineItem $lineItem, int $heldByUserId): InventoryHold
    {
        return DB::transaction(function () use ($lineItem, $heldByUserId) {
            $hold = InventoryHold::create([
                'screen_id'            => $lineItem->screen_id,
                'booking_line_item_id' => $lineItem->id,
                'booking_id'           => $lineItem->booking_id,
                'held_by'              => $heldByUserId,
                'hold_start'           => $lineItem->start_date,
                'hold_end'             => $lineItem->end_date,
                'spot_duration'        => $lineItem->spot_duration,
                'spots_per_hour'       => $lineItem->spots_per_hour,
                'hold_type'            => 'soft',
                'status'               => 'active',
                'expires_at'           => now()->addHours(24),
            ]);

            $this->recalculateAvailability($lineItem->screen_id, $lineItem->start_date, $lineItem->end_date);

            Log::info('Soft hold created', [
                'hold_id'    => $hold->id,
                'screen_id'  => $lineItem->screen_id,
                'booking_id' => $lineItem->booking_id,
                'period'     => "{$lineItem->start_date} → {$lineItem->end_date}",
            ]);

            return $hold;
        });
    }

    /**
     * Convert a soft hold to a hard hold (confirmed, no expiry).
     */
    public function convertToHardHold(InventoryHold $hold): void
    {
        $hold->update([
            'hold_type'  => 'hard',
            'expires_at' => null,
            'status'     => 'active',
        ]);

        Log::info('Hold converted to hard', ['hold_id' => $hold->id]);
    }

    /**
     * Release an inventory hold.
     */
    public function releaseHold(InventoryHold $hold, string $reason = 'manual'): void
    {
        $hold->update([
            'status'         => 'released',
            'released_at'    => now(),
            'release_reason' => $reason,
        ]);

        $this->recalculateAvailability($hold->screen_id, $hold->hold_start, $hold->hold_end);

        Log::info('Hold released', ['hold_id' => $hold->id, 'reason' => $reason]);
    }

    /**
     * Release all active holds for a given booking.
     */
    public function releaseAllForBooking(int $bookingId, string $reason = 'booking_cancelled'): void
    {
        $holds = InventoryHold::where('booking_id', $bookingId)
            ->where('status', 'active')
            ->get();

        foreach ($holds as $hold) {
            $this->releaseHold($hold, $reason);
        }
    }

    /**
     * Expire stale soft holds that have passed their expires_at.
     * Called by scheduled command every 15 minutes.
     */
    public function expireStaleHolds(): int
    {
        $stale = InventoryHold::expired()->get();

        foreach ($stale as $hold) {
            $hold->update([
                'status'         => 'expired',
                'released_at'    => now(),
                'release_reason' => 'auto_expired',
            ]);
            $this->recalculateAvailability($hold->screen_id, $hold->hold_start, $hold->hold_end);
        }

        if ($stale->count()) {
            Log::info("Expired {$stale->count()} stale inventory holds");
        }

        return $stale->count();
    }

    /**
     * Recalculate and persist availability log for a screen over a date range.
     */
    public function recalculateAvailability(int $screenId, Carbon|string $start, Carbon|string $end): void
    {
        $screen = Screen::find($screenId);
        if (! $screen) {
            return;
        }

        $startDate = Carbon::parse($start);
        $endDate   = Carbon::parse($end);

        $current = $startDate->copy();
        while ($current->lte($endDate)) {
            $dateStr = $current->toDateString();

            // Count slots held on this specific date
            $heldSlots = InventoryHold::where('screen_id', $screenId)
                ->where('status', 'active')
                ->where('hold_start', '<=', $dateStr)
                ->where('hold_end', '>=', $dateStr)
                ->sum('spots_per_hour');

            $totalDailySlots = $screen->total_slots_per_hour * $screen->operational_hours;
            $heldTotal       = $heldSlots * $screen->operational_hours;
            $availableTotal  = max(0, $totalDailySlots - $heldTotal);
            $fillRate        = $totalDailySlots > 0
                ? round(($heldTotal / $totalDailySlots) * 100, 2)
                : 0;

            ScreenAvailabilityLog::updateOrCreate(
                ['screen_id' => $screenId, 'date' => $dateStr],
                [
                    'total_slots_per_hour' => $screen->total_slots_per_hour,
                    'operational_hours'    => $screen->operational_hours,
                    'total_daily_slots'    => $totalDailySlots,
                    'sold_slots'           => 0, // TODO: add sold tracking from confirmed bookings
                    'held_slots'           => $heldTotal,
                    'available_slots'      => $availableTotal,
                    'fill_rate_pct'        => $fillRate,
                    'calculated_at'        => now(),
                ]
            );

            $current->addDay();
        }
    }
}
