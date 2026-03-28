<?php

namespace App\Console\Commands;

use App\Jobs\DailyCashflowSnapshotJob;
use App\Models\CashflowSnapshot;
use App\Services\CollectionService;
use Illuminate\Console\Command;

class CashflowSnapshotCommand extends Command
{
    protected $signature   = 'cashflow:snapshot';
    protected $description = 'Take a daily cashflow snapshot and store in cashflow_snapshots table';

    public function handle(CollectionService $service): int
    {
        dispatch_sync(new DailyCashflowSnapshotJob());

        $snapshot = CashflowSnapshot::whereDate('snapshot_date', today())->first();
        if ($snapshot) {
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Total AR',          number_format($snapshot->total_ar, 0, ',', '.') . ' VND'],
                    ['Total Overdue',     number_format($snapshot->total_overdue, 0, ',', '.') . ' VND'],
                    ['Due This Month',    number_format($snapshot->due_this_month, 0, ',', '.') . ' VND'],
                    ['Forecast 30 Days',  number_format($snapshot->forecast_30_days, 0, ',', '.') . ' VND'],
                    ['Overdue Count',     $snapshot->overdue_count],
                    ['Due Soon (7d)',     $snapshot->due_soon_count],
                ]
            );
        }

        return Command::SUCCESS;
    }
}
