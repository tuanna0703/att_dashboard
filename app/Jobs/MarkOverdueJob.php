<?php

namespace App\Jobs;

use App\Services\PaymentScheduleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class MarkOverdueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function handle(PaymentScheduleService $service): void
    {
        $count = $service->markOverdue();
        Log::info("[MarkOverdueJob] Completed. {$count} payment schedules marked as overdue.");
    }

    public function failed(Throwable $exception): void
    {
        Log::error("[MarkOverdueJob] Failed: {$exception->getMessage()}");
    }
}
