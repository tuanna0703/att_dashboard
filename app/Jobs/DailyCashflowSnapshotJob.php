<?php

namespace App\Jobs;

use App\Models\CashflowSnapshot;
use App\Services\CollectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class DailyCashflowSnapshotJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function handle(CollectionService $service): void
    {
        $today = Carbon::today()->toDateString();

        CashflowSnapshot::updateOrCreate(
            ['snapshot_date' => $today],
            [
                'total_ar'         => $service->totalAR(),
                'total_overdue'    => $service->overdueList()->sum('amount'),
                'due_this_month'   => $service->dueThisMonth(),
                'forecast_30_days' => $service->forecastNext30Days(),
                'overdue_count'    => $service->overdueList()->count(),
                'due_soon_count'   => $service->dueSoon(7)->count(),
            ]
        );

        Log::info("[DailyCashflowSnapshotJob] Snapshot saved for {$today}.");
    }

    public function failed(Throwable $exception): void
    {
        Log::error('[DailyCashflowSnapshotJob] Failed: ' . $exception->getMessage());
    }
}
