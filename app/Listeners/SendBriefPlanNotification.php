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
use App\Filament\Resources\BriefResource;
use App\Models\User;
use Filament\Notifications\Actions\Action as NotifAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

class SendBriefPlanNotification
{
    public function handle(BriefPlanEvent $event): void
    {
        $recipients = $this->resolveRecipients($event);

        if ($recipients->isEmpty()) {
            return;
        }

        $title   = $this->buildTitle($event);
        $body    = $this->buildBody($event);
        $icon    = $this->resolveIcon($event);
        $color   = $this->resolveColor($event);
        $briefId = $this->resolveBriefId($event);

        foreach ($recipients as $user) {
            // Không tự notify chính mình
            if ($user->id === $event->causer->id) {
                continue;
            }

            Notification::make()
                ->title($title)
                ->body($body)
                ->icon($icon)
                ->iconColor($color)
                ->actions([
                    NotifAction::make('view')
                        ->label('Xem Brief')
                        ->url(BriefResource::getUrl('view', ['record' => $briefId])),
                ])
                ->sendToDatabase($user);
        }
    }

    // ─── Recipients ───────────────────────────────────────────────────────────

    private function resolveRecipients(BriefPlanEvent $event): Collection
    {
        return match(true) {

            // Brief tạo xong hoặc gửi AdOps → notify AdOps được assign
            $event instanceof BriefCreated,
            $event instanceof BriefSentToAdops => $this->resolveAdopsRecipients($event),

            // AdOps tạo Plan → notify Sale phụ trách Brief
            $event instanceof PlanCreated => collect([
                $event->subject->brief->sale,
            ])->filter(),

            // AdOps gửi Plan duyệt → notify Sale phụ trách Brief
            $event instanceof PlanSubmitted => collect([
                $event->subject->brief->sale,
            ])->filter(),

            // Sale accept/re-plan/reject → notify AdOps phụ trách Plan
            $event instanceof PlanAccepted,
            $event instanceof PlanRePlanRequested,
            $event instanceof PlanRejected => collect([
                $event->subject->createdBy,
            ])->filter(),

            default => collect(),
        };
    }

    private function resolveAdopsRecipients(BriefPlanEvent $event): Collection
    {
        $brief = $event->subject; // subject là Brief

        // AdOps được assign trực tiếp
        $adops = collect();
        if ($brief->adops_id) {
            $adops->push($brief->adops);
        }

        // Nếu chưa assign, notify tất cả user có role adops
        if ($adops->isEmpty()) {
            $adops = User::role('adops')->get();
        }

        return $adops->filter();
    }

    // ─── Message builders ─────────────────────────────────────────────────────

    private function buildTitle(BriefPlanEvent $event): string
    {
        $ctx = $event->context;

        return match(true) {
            $event instanceof BriefCreated =>
                "Brief mới: {$event->subject->brief_no}",

            $event instanceof BriefSentToAdops =>
                "Brief {$event->subject->brief_no} được gửi cho bạn",

            $event instanceof PlanCreated =>
                "Plan mới cho Brief {$event->subject->brief->brief_no}",

            $event instanceof PlanSubmitted =>
                "Plan {$ctx['plan_no']} v{$ctx['version']} chờ bạn duyệt",

            $event instanceof PlanAccepted =>
                "Plan {$ctx['plan_no']} đã được chấp nhận!",

            $event instanceof PlanRePlanRequested =>
                "Plan {$ctx['plan_no']} cần điều chỉnh",

            $event instanceof PlanRejected =>
                "Plan {$ctx['plan_no']} bị từ chối",

            default => 'Thông báo mới',
        };
    }

    private function buildBody(BriefPlanEvent $event): string
    {
        $ctx  = $event->context;
        $name = $event->causer->name;

        return match(true) {
            $event instanceof BriefCreated =>
                "{$event->subject->campaign_name} — tạo bởi {$name}",

            $event instanceof BriefSentToAdops =>
                "{$event->subject->campaign_name} — được giao bởi {$name}",

            $event instanceof PlanCreated =>
                "{$event->subject->brief->campaign_name} — AdOps: {$name}",

            $event instanceof PlanSubmitted =>
                "{$event->subject->brief->campaign_name} — gửi bởi {$name}",

            $event instanceof PlanAccepted =>
                "Brief sẽ chuyển sang trạng thái Confirmed. Bạn có thể tạo Booking.",

            $event instanceof PlanRePlanRequested =>
                isset($ctx['comment']) ? "Lý do: {$ctx['comment']}" : "Sale yêu cầu xem xét lại kế hoạch.",

            $event instanceof PlanRejected =>
                isset($ctx['comment']) ? "Lý do từ chối: {$ctx['comment']}" : "Khách hàng không đồng ý với plan này.",

            default => '',
        };
    }

    private function resolveIcon(BriefPlanEvent $event): string
    {
        return match(true) {
            $event instanceof BriefCreated          => 'heroicon-o-document-plus',
            $event instanceof BriefSentToAdops      => 'heroicon-o-paper-airplane',
            $event instanceof PlanCreated           => 'heroicon-o-clipboard-document-list',
            $event instanceof PlanSubmitted         => 'heroicon-o-paper-airplane',
            $event instanceof PlanAccepted          => 'heroicon-o-check-badge',
            $event instanceof PlanRePlanRequested   => 'heroicon-o-arrow-path',
            $event instanceof PlanRejected          => 'heroicon-o-x-circle',
            default                                 => 'heroicon-o-bell',
        };
    }

    private function resolveColor(BriefPlanEvent $event): string
    {
        return match(true) {
            $event instanceof BriefCreated          => 'gray',
            $event instanceof BriefSentToAdops      => 'info',
            $event instanceof PlanCreated           => 'info',
            $event instanceof PlanSubmitted         => 'warning',
            $event instanceof PlanAccepted          => 'success',
            $event instanceof PlanRePlanRequested   => 'warning',
            $event instanceof PlanRejected          => 'danger',
            default                                 => 'gray',
        };
    }

    private function resolveBriefId(BriefPlanEvent $event): int
    {
        return match(true) {
            $event instanceof BriefCreated,
            $event instanceof BriefSentToAdops => $event->subject->id,
            default                            => $event->subject->brief_id,
        };
    }
}
