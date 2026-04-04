<?php

namespace App\Listeners;

use App\Events\Brief\BriefCreated;
use App\Events\Brief\BriefSentToAdops;
use App\Events\BriefPlanEvent;
use App\Events\Plan\PlanAccepted;
use App\Events\Plan\PlanCreated;
use App\Events\Plan\PlanRejected;
use App\Events\Plan\PlanRePlanRequested;
use App\Events\Plan\PlanSubmitted;
use App\Models\ActivityLog;
use App\Models\Brief;
use App\Models\Plan;

class RecordActivityLog
{
    public function handle(BriefPlanEvent $event): void
    {
        ActivityLog::create([
            'log_name'     => $this->resolveLogName($event),
            'event'        => $this->resolveEventKey($event),
            'subject_type' => get_class($event->subject),
            'subject_id'   => $event->subject->id,
            'causer_id'    => $event->causer->id,
            'causer_name'  => $event->causer->name,
            'description'  => $this->buildDescription($event),
            'properties'   => $this->buildProperties($event),
        ]);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function resolveLogName(BriefPlanEvent $event): string
    {
        return match(true) {
            $event instanceof BriefCreated,
            $event instanceof BriefSentToAdops  => 'brief',
            default                             => 'plan',
        };
    }

    private function resolveEventKey(BriefPlanEvent $event): string
    {
        return match(true) {
            $event instanceof BriefCreated          => 'brief.created',
            $event instanceof BriefSentToAdops      => 'brief.sent_to_adops',
            $event instanceof PlanCreated           => 'plan.created',
            $event instanceof PlanSubmitted         => 'plan.submitted',
            $event instanceof PlanAccepted          => 'plan.accepted',
            $event instanceof PlanRePlanRequested   => 'plan.re_plan',
            $event instanceof PlanRejected          => 'plan.rejected',
            default                                 => 'unknown',
        };
    }

    private function buildDescription(BriefPlanEvent $event): string
    {
        $causer = $event->causer->name;
        $ctx    = $event->context;

        return match(true) {
            $event instanceof BriefCreated =>
                "{$causer} tạo Brief {$event->subject->brief_no}",

            $event instanceof BriefSentToAdops =>
                "{$causer} gửi Brief {$event->subject->brief_no} cho AdOps {$ctx['adops_name']}",

            $event instanceof PlanCreated =>
                "{$causer} tạo Plan {$ctx['plan_no']} v{$ctx['version']}",

            $event instanceof PlanSubmitted =>
                "{$causer} gửi Plan {$ctx['plan_no']} v{$ctx['version']} cho Sale duyệt",

            $event instanceof PlanAccepted =>
                "{$causer} chấp nhận Plan {$ctx['plan_no']} v{$ctx['version']}",

            $event instanceof PlanRePlanRequested =>
                "{$causer} yêu cầu điều chỉnh Plan {$ctx['plan_no']} v{$ctx['version']}",

            $event instanceof PlanRejected =>
                "{$causer} từ chối Plan {$ctx['plan_no']} v{$ctx['version']}",

            default => "{$causer} thực hiện hành động trên " . class_basename($event->subject),
        };
    }

    private function buildProperties(BriefPlanEvent $event): array
    {
        $base = ['causer_role' => $event->causer->getRoleNames()->first()];

        if ($event->subject instanceof Brief) {
            $base['brief_no']       = $event->subject->brief_no;
            $base['campaign_name']  = $event->subject->campaign_name;
        }

        if ($event->subject instanceof Plan) {
            $base['plan_no']        = $event->subject->plan_no ?? ($event->context['plan_no'] ?? null);
            $base['version']        = $event->subject->version ?? ($event->context['version'] ?? null);
            $base['brief_no']       = $event->subject->brief->brief_no ?? null;
            $base['campaign_name']  = $event->subject->brief->campaign_name ?? null;
        }

        // Merge extra context (comment, flagged_items, etc.)
        return array_merge($base, $event->context);
    }
}
