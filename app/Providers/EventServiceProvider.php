<?php

namespace App\Providers;

use App\Events\Brief\BriefCreated;
use App\Events\Brief\BriefSentToAdops;
use App\Events\Plan\PlanAccepted;
use App\Events\Plan\PlanCreated;
use App\Events\Plan\PlanRejected;
use App\Events\Plan\PlanRePlanRequested;
use App\Events\Plan\PlanSubmitted;
use App\Listeners\RecordActivityLog;
use App\Listeners\SendBriefPlanNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [

        // ── Brief events ──────────────────────────────────────────────────────
        BriefCreated::class => [
            RecordActivityLog::class,
            SendBriefPlanNotification::class,
        ],

        BriefSentToAdops::class => [
            RecordActivityLog::class,
            SendBriefPlanNotification::class,
        ],

        // ── Plan events ───────────────────────────────────────────────────────
        PlanCreated::class => [
            RecordActivityLog::class,
            SendBriefPlanNotification::class,
        ],

        PlanSubmitted::class => [
            RecordActivityLog::class,
            SendBriefPlanNotification::class,
        ],

        PlanAccepted::class => [
            RecordActivityLog::class,
            SendBriefPlanNotification::class,
        ],

        PlanRePlanRequested::class => [
            RecordActivityLog::class,
            SendBriefPlanNotification::class,
        ],

        PlanRejected::class => [
            RecordActivityLog::class,
            SendBriefPlanNotification::class,
        ],
    ];
}
