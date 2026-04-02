<?php

namespace App\Observers;

use App\Models\CampaignTraffic;
use App\Services\ReadyToAirGateService;

class CampaignTrafficObserver
{
    public function __construct(private ReadyToAirGateService $gateService) {}

    /** Update gate campaign_trafficked + qa_passed when traffic status changes */
    public function updated(CampaignTraffic $traffic): void
    {
        if ($traffic->wasChanged('status')) {
            $this->gateService->evaluate($traffic->booking);
        }
    }
}
