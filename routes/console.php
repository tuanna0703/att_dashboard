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

// 00:30 — mark overdue schedules (phải chạy trước reminder)
Schedule::job(new MarkOverdueJob)->dailyAt('00:30')
    ->name('mark-overdue')
    ->withoutOverlapping();

// 08:00 — gửi reminder nội bộ cho các đợt quá hạn
Schedule::job(new SendOverdueReminderJob)->dailyAt('08:00')
    ->name('send-overdue-reminder')
    ->withoutOverlapping();

// 01:00 — sinh lịch thanh toán cho hợp đồng subscription
Schedule::job(new GenerateSubscriptionSchedulesJob)->dailyAt('01:00')
    ->name('generate-subscription-schedules')
    ->withoutOverlapping();

// 02:00 — snapshot cashflow metrics hàng ngày cho dashboard
Schedule::job(new DailyCashflowSnapshotJob)->dailyAt('02:00')
    ->name('daily-cashflow-snapshot')
    ->withoutOverlapping();
