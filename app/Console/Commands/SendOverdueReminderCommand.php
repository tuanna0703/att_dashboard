<?php

namespace App\Console\Commands;

use App\Jobs\SendOverdueReminderJob;
use Illuminate\Console\Command;

class SendOverdueReminderCommand extends Command
{
    protected $signature   = 'reminders:send-overdue';
    protected $description = 'Send overdue payment reminders to responsible users (runs synchronously)';

    public function handle(): int
    {
        $this->info('Dispatching SendOverdueReminderJob synchronously...');
        dispatch_sync(new SendOverdueReminderJob());
        $this->info('Done.');
        return Command::SUCCESS;
    }
}
