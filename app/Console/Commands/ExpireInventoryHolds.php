<?php

namespace App\Console\Commands;

use App\Services\InventoryService;
use Illuminate\Console\Command;

class ExpireInventoryHolds extends Command
{
    protected $signature   = 'inventory:expire-holds';
    protected $description = 'Expire stale soft inventory holds that have passed their expiry time';

    public function handle(InventoryService $inventoryService): int
    {
        $count = $inventoryService->expireStaleHolds();
        $this->info("Expired {$count} stale hold(s).");
        return Command::SUCCESS;
    }
}
