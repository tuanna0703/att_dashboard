<?php

namespace App\Events\Brief;

use App\Events\BriefPlanEvent;

/**
 * Fired when a Brief is assigned and sent to AdOps.
 *
 * context keys:
 *   adops_id   (int)    – the assigned AdOps user id
 *   adops_name (string) – snapshot of AdOps name
 */
class BriefSentToAdops extends BriefPlanEvent {}
