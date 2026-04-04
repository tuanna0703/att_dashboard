<?php

namespace App\Events\Plan;

use App\Events\BriefPlanEvent;

/**
 * Fired when Sale rejects a Plan (customer declined) → Brief cancelled/rejected.
 *
 * context keys:
 *   version (int)
 *   plan_no (string)
 *   comment (string) – reason for rejection
 */
class PlanRejected extends BriefPlanEvent {}
