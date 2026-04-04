<?php

namespace App\Events\Plan;

use App\Events\BriefPlanEvent;

/**
 * Fired when Sale accepts a Plan → Brief moves to confirmed.
 *
 * context keys:
 *   version  (int)
 *   plan_no  (string)
 */
class PlanAccepted extends BriefPlanEvent {}
