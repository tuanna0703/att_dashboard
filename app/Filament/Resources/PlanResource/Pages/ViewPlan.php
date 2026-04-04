<?php

namespace App\Filament\Resources\PlanResource\Pages;

use App\Events\Plan\PlanAccepted;
use App\Events\Plan\PlanRejected;
use App\Events\Plan\PlanRePlanRequested;
use App\Events\Plan\PlanSubmitted;
use App\Filament\Resources\BriefResource;
use App\Filament\Resources\PlanResource;
use App\Filament\Resources\PlanResource\RelationManagers\LineItemsRelationManager;
use App\Models\Plan;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components\Actions as InfolistActions;
use Filament\Infolists\Components\Actions\Action as InfolistAction;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\Tabs\Tab;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Storage;

class ViewPlan extends ViewRecord
{
    protected static string $resource = PlanResource::class;

    // ─── Relation managers ───────────────────────────────────────────────────

    public function getRelationManagers(): array
    {
        return PlanResource::getRelationManagers();
    }

    // ─── Title ────────────────────────────────────────────────────────────────

    public function getTitle(): string
    {
        return $this->record->plan_no . ' — ' . ($this->record->brief?->campaign_name ?? '');
    }

    // ─── Header actions ───────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([

                // ── Edit (AdOps, trạng thái draft hoặc re_plan) ────────────────
                Actions\EditAction::make()
                    ->visible(fn () => in_array($this->record->status, ['draft', 're_plan'])
                        && $this->record->adops_id === auth()->id()
                    ),

                // ── AdOps: Tạo plan điều chỉnh ────────────────────────────────
                Actions\Action::make('create_revision')
                    ->label('Tạo Plan điều chỉnh')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn () => $this->record->status === 're_plan'
                        && auth()->user()->hasAnyRole(['adops', 'ceo', 'coo'])
                    )
                    ->url(fn () => PlanResource::getUrl('create', [
                        'brief_id'     => $this->record->brief_id,
                        'from_plan_id' => $this->record->id,
                    ])),

                // ── AdOps: Gửi plan cho Sale duyệt ────────────────────────────
                Actions\Action::make('submit')
                    ->label('Gửi duyệt')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->visible(fn () => $this->record->status === 'draft'
                        && $this->record->adops_id === auth()->id()
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Xác nhận gửi Plan cho Sale review?')
                    ->action(function () {
                        $this->record->update(['status' => 'submitted']);
                        $this->record->brief->update(['status' => 'planning_ready']);

                        event(new PlanSubmitted(
                            subject: $this->record,
                            causer:  auth()->user(),
                            context: [
                                'plan_no' => $this->record->plan_no,
                                'version' => $this->record->version,
                            ]
                        ));

                        Notification::make()->title('Đã gửi Plan cho Sale review')->success()->send();
                        $this->refreshFormData(['status']);
                    }),

                // ── Sale: Chấp nhận plan ────────────────────────────────────
                Actions\Action::make('accept')
                    ->label('Chấp nhận')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn () => $this->record->status === 'submitted'
                        && auth()->user()->hasAnyRole(['sale', 'ceo', 'coo'])
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Xác nhận chấp nhận Plan này?')
                    ->modalDescription('Brief sẽ chuyển sang trạng thái "Confirmed" và có thể tạo Booking.')
                    ->action(function () {
                        $this->record->update([
                            'status'       => 'accepted',
                            'responded_by' => auth()->id(),
                            'responded_at' => now(),
                        ]);

                        $this->record->brief->plans()
                            ->where('id', '!=', $this->record->id)
                            ->whereNotIn('status', ['accepted', 'rejected'])
                            ->update(['status' => 're_plan']);

                        $this->record->brief->update(['status' => 'confirmed']);

                        event(new PlanAccepted(
                            subject: $this->record,
                            causer:  auth()->user(),
                            context: [
                                'plan_no' => $this->record->plan_no,
                                'version' => $this->record->version,
                            ]
                        ));

                        Notification::make()->title('Plan được chấp nhận — Brief đã confirmed')->success()->send();
                        $this->refreshFormData(['status', 'responded_by', 'responded_at']);
                    }),

                // ── Sale: Yêu cầu điều chỉnh ──────────────────────────────────
                Actions\Action::make('re_plan')
                    ->label('Yêu cầu điều chỉnh')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn () => $this->record->status === 'submitted'
                        && auth()->user()->hasAnyRole(['sale', 'ceo', 'coo'])
                    )
                    ->form([
                        Forms\Components\Textarea::make('sale_comment')
                            ->label('Điểm cần trao đổi / điều chỉnh')
                            ->required()
                            ->rows(4)
                            ->placeholder('Mô tả các điểm AdOps cần điều chỉnh...'),
                    ])
                    ->modalHeading('Yêu cầu AdOps điều chỉnh Plan')
                    ->action(function (array $data) {
                        $this->record->update([
                            'status'       => 're_plan',
                            'sale_comment' => $data['sale_comment'],
                            'responded_by' => auth()->id(),
                            'responded_at' => now(),
                        ]);

                        $this->record->brief->update(['status' => 'sent_to_adops']);

                        event(new PlanRePlanRequested(
                            subject: $this->record,
                            causer:  auth()->user(),
                            context: [
                                'plan_no' => $this->record->plan_no,
                                'version' => $this->record->version,
                                'comment' => $data['sale_comment'],
                            ]
                        ));

                        Notification::make()->title('Đã gửi yêu cầu điều chỉnh cho AdOps')->warning()->send();
                        $this->refreshFormData(['status', 'sale_comment', 'responded_by', 'responded_at']);
                    }),

                // ── Sale: Từ chối plan ─────────────────────────────────────────
                Actions\Action::make('reject')
                    ->label('Từ chối')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn () => $this->record->status === 'submitted'
                        && auth()->user()->hasAnyRole(['sale', 'ceo', 'coo'])
                    )
                    ->form([
                        Forms\Components\Textarea::make('sale_comment')
                            ->label('Lý do từ chối')
                            ->required()
                            ->rows(3),
                    ])
                    ->modalHeading('Từ chối Plan — Brief sẽ bị đóng')
                    ->modalDescription('Khách hàng từ chối. Brief sẽ chuyển trạng thái "Từ chối".')
                    ->action(function (array $data) {
                        $this->record->update([
                            'status'       => 'rejected',
                            'sale_comment' => $data['sale_comment'],
                            'responded_by' => auth()->id(),
                            'responded_at' => now(),
                        ]);

                        $this->record->brief->update(['status' => 'rejected']);

                        event(new PlanRejected(
                            subject: $this->record,
                            causer:  auth()->user(),
                            context: [
                                'plan_no' => $this->record->plan_no,
                                'version' => $this->record->version,
                                'comment' => $data['sale_comment'],
                            ]
                        ));

                        Notification::make()->title('Brief đã bị từ chối và đóng lại')->danger()->send();
                        $this->refreshFormData(['status', 'sale_comment', 'responded_by', 'responded_at']);
                    }),

            ]),
        ];
    }

    // ─── Infolist ─────────────────────────────────────────────────────────────

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([

            Tabs::make()->tabs([

                // ── Tab General ───────────────────────────────────────────────
                Tab::make('General')
                    ->schema([
                        TextEntry::make('plan_no')
                            ->label('Mã Plan')
                            ->weight('bold')
                            ->copyable(),

                        TextEntry::make('version')
                            ->label('Phiên bản')
                            ->formatStateUsing(fn ($state) => 'v' . $state),

                        TextEntry::make('status')
                            ->label('Trạng thái')
                            ->badge()
                            ->formatStateUsing(fn ($state) => Plan::$statuses[$state] ?? $state)
                            ->color(fn ($state) => Plan::$statusColors[$state] ?? 'gray'),

                        TextEntry::make('brief.brief_no')
                            ->label('Brief')
                            ->url(fn ($record) => BriefResource::getUrl('view', ['record' => $record->brief_id]))
                            ->color('primary'),

                        TextEntry::make('adops.name')
                            ->label('AdOps phụ trách')
                            ->placeholder('—'),

                        TextEntry::make('budget')
                            ->label('Ngân sách kế hoạch')
                            ->money(fn ($record) => $record->brief?->currency ?? 'VND')
                            ->weight('bold')
                            ->placeholder('—'),

                        TextEntry::make('screen_count')
                            ->label('Số line items')
                            ->placeholder('—'),

                        TextEntry::make('start_date')
                            ->label('Ngày bắt đầu')
                            ->getStateUsing(fn ($record) => $record->lineItems->min('start_date'))
                            ->date('d/m/Y')
                            ->placeholder('—'),

                        TextEntry::make('end_date')
                            ->label('Ngày kết thúc')
                            ->getStateUsing(fn ($record) => $record->lineItems->max('end_date'))
                            ->date('d/m/Y')
                            ->placeholder('—'),
                    ])->columns(3),

                // ── Tab Management ────────────────────────────────────────────
                Tab::make('Management')->schema([
                    TextEntry::make('note')
                        ->label('Ghi chú kế hoạch (AdOps)')
                        ->placeholder('—')
                        ->columnSpanFull(),

                    InfolistActions::make([
                        InfolistAction::make('download_file')
                            ->label('Download')
                            ->icon('heroicon-o-arrow-down-tray')
                            ->color('gray')
                            ->action(fn ($record) => Storage::download($record->file_path))
                            ->visible(fn ($record) => (bool) $record->file_path),
                    ])
                        ->label('File kế hoạch')
                        ->columnSpanFull(),

                    TextEntry::make('sale_comment')
                        ->label('Comment của Sale')
                        ->placeholder('Chưa có phản hồi')
                        ->columnSpanFull(),

                    TextEntry::make('respondedBy.name')
                        ->label('Người phản hồi')
                        ->placeholder('—'),

                    TextEntry::make('responded_at')
                        ->label('Thời gian phản hồi')
                        ->dateTime('d/m/Y H:i')
                        ->placeholder('—'),
                ])->columns(2),

            ])->columnSpanFull(),
        ]);
    }
}
