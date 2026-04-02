<?php

namespace App\Observers;

use App\Models\CreativeSubmission;
use App\Services\ReadyToAirGateService;

class CreativeSubmissionObserver
{
    public function __construct(private ReadyToAirGateService $gateService) {}

    /** Update gate creative_approved when submission is approved */
    public function updated(CreativeSubmission $submission): void
    {
        if ($submission->wasChanged('status')) {
            $this->gateService->evaluate($submission->booking);
        }
    }
}
