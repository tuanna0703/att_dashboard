<?php

use App\Jobs\DailyCashflowSnapshotJob;
use App\Jobs\GenerateSubscriptionSchedulesJob;
use App\Jobs\MarkOverdueJob;
use App\Jobs\SendOverdueReminderJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily: mark overdue payment schedules
Schedule::job(new MarkOverdueJob)->dailyAt('00:30');

// Daily: send internal overdue reminders
Schedule::job(new SendOverdueReminderJob)->dailyAt('08:00');

// Daily: generate upcoming subscription schedules
Schedule::job(new GenerateSubscriptionSchedulesJob)->dailyAt('01:00');

// Daily: snapshot cashflow metrics for dashboard
Schedule::job(new DailyCashflowSnapshotJob)->dailyAt('02:00');
