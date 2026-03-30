<?php

namespace App\Console\Commands;

use App\Mail\OverdueSummaryMail;
use App\Mail\UpcomingPaymentsMail;
use App\Models\PaymentSchedule;
use App\Models\ReportSubscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendReportSubscriptionsCommand extends Command
{
    protected $signature   = 'reports:send-subscriptions {--force : Send regardless of schedule time}';
    protected $description = 'Send scheduled report emails based on active report subscriptions';

    public function handle(): int
    {
        $subscriptions = ReportSubscription::where('is_active', true)->get();

        if ($subscriptions->isEmpty()) {
            $this->info('No active report subscriptions.');
            return Command::SUCCESS;
        }

        $sent = 0;

        foreach ($subscriptions as $subscription) {
            if (! $this->option('force') && ! $subscription->isDue()) {
                continue;
            }

            $recipients = $subscription->resolveRecipientUsers();

            if ($recipients->isEmpty()) {
                $this->warn("Subscription [{$subscription->name}]: no recipients resolved, skipping.");
                continue;
            }

            $schedules = match ($subscription->report_type) {
                'overdue_summary'   => $this->fetchOverdueSchedules(),
                'upcoming_payments' => $this->fetchUpcomingSchedules(),
                default             => null,
            };

            if ($schedules === null) {
                continue;
            }

            foreach ($recipients as $user) {
                try {
                    $mailable = match ($subscription->report_type) {
                        'overdue_summary'   => new OverdueSummaryMail($schedules, $subscription->name),
                        'upcoming_payments' => new UpcomingPaymentsMail($schedules, $subscription->name),
                    };
                    Mail::to($user->email)->send($mailable);
                    $sent++;
                    $this->line("  Sent to {$user->email}");
                } catch (\Throwable $e) {
                    $this->error("  Failed to send to {$user->email}: " . $e->getMessage());
                    Log::error('[reports:send-subscriptions] Failed', [
                        'subscription' => $subscription->id,
                        'user'         => $user->id,
                        'error'        => $e->getMessage(),
                    ]);
                }
            }

            $subscription->update(['last_sent_at' => now()]);
            $this->info("Subscription [{$subscription->name}]: sent to {$recipients->count()} recipient(s).");
        }

        $this->info("Done. {$sent} email(s) sent.");
        return Command::SUCCESS;
    }

    private function fetchOverdueSchedules(): \Illuminate\Support\Collection
    {
        return PaymentSchedule::with(['contract.customer', 'responsibleUser'])
            ->where('status', 'overdue')
            ->orderBy('due_date')
            ->get();
    }

    private function fetchUpcomingSchedules(): \Illuminate\Support\Collection
    {
        return PaymentSchedule::with(['contract.customer', 'responsibleUser'])
            ->whereIn('status', ['pending', 'invoiced', 'partially_paid'])
            ->whereBetween('due_date', [today(), today()->addDays(30)])
            ->orderBy('due_date')
            ->get();
    }
}
