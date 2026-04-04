<?php

namespace App\Events\Plan;

use App\Events\BriefPlanEvent;

/**
 * Fired when Sale requests changes on a Plan (re_plan).
 *
 * context keys:
 *   version (int)
 *   plan_no (string)
 *   comment (string) – Sale's feedback / instructions
 */
class PlanRePlanRequested extends BriefPlanEvent {}
