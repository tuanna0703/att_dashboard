<?php

namespace App\Events\Plan;

use App\Events\BriefPlanEvent;

/**
 * Fired when AdOps creates a new Plan from a Brief.
 *
 * context keys:
 *   version  (int)    – plan version number
 *   plan_no  (string) – generated plan code
 */
class PlanCreated extends BriefPlanEvent {}
