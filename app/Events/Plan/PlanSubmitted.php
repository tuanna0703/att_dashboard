<?php

namespace App\Events\Plan;

use App\Events\BriefPlanEvent;

/**
 * Fired when AdOps submits a Plan to Sale for review.
 *
 * context keys:
 *   version  (int)
 *   plan_no  (string)
 */
class PlanSubmitted extends BriefPlanEvent {}
