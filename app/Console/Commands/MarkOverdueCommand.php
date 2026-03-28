<?php

namespace App\Console\Commands;

use App\Services\PaymentScheduleService;
use Illuminate\Console\Command;

class MarkOverdueCommand extends Command
{
    protected $signature   = 'schedules:mark-overdue';
    protected $description = 'Mark payment schedules as overdue where due_date is past and status is not paid';

    public function handle(PaymentScheduleService $service): int
    {
        $count = $service->markOverdue();
        $this->info("Done. {$count} payment schedule(s) marked as overdue.");
        return Command::SUCCESS;
    }
}
