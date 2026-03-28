<?php

namespace App\Jobs;

use App\Models\PaymentSchedule;
use App\Models\User;
use App\Notifications\OverduePaymentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendOverdueReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 120;

    public function handle(): void
    {
        $overdueSchedules = PaymentSchedule::with(['contract.customer', 'responsibleUser'])
            ->where('status', 'overdue')
            ->get();

        if ($overdueSchedules->isEmpty()) {
            Log::info('[SendOverdueReminderJob] No overdue schedules found. Skipping.');
            return;
        }

        // Group overdue schedules by responsible_user_id
        $grouped = $overdueSchedules->groupBy('responsible_user_id');

        $notified = 0;

        foreach ($grouped as $userId => $schedules) {
            // Schedules with no responsible user → notify all Finance role users
            if (is_null($userId)) {
                $financeUsers = User::role('finance')->get();
                foreach ($financeUsers as $user) {
                    $user->notify(new OverduePaymentNotification($schedules));
                    $notified++;
                }
                continue;
            }

            $user = User::find($userId);
            if ($user) {
                $user->notify(new OverduePaymentNotification($schedules));
                $notified++;
            }
        }

        Log::info("[SendOverdueReminderJob] Sent reminders to {$notified} user(s). Total overdue: {$overdueSchedules->count()} schedules.");
    }

    public function failed(Throwable $exception): void
    {
        Log::error('[SendOverdueReminderJob] Failed: ' . $exception->getMessage());
    }
}
