<?php

namespace App\Filament\Resources\BriefResource\RelationManagers;

use App\Events\Plan\PlanAccepted;
use App\Events\Plan\PlanRejected;
use App\Events\Plan\PlanRePlanRequested;
use App\Events\Plan\PlanSubmitted;
use App\Filament\Resources\PlanResource;
use App\Models\Plan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class PlansRelationManager extends RelationManager
{
    protected static string $relationship = 'plans';
    protected static ?string $title       = 'Plans của AdOps';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function canCreate(): bool
    {
        return auth()->user()->hasAnyRole(['adops', 'ceo', 'coo']);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Thông tin kế hoạch')->schema([
                Forms\Components\TextInput::make('plan_no')
                    ->label('Mã Plan')
                    ->placeholder('Tự động tạo')
                    ->disabled()
                    ->dehydrated(false),

                Forms\Components\TextInput::make('version')
                    ->label('Phiên bản')
                    ->disabled()
                    ->dehydrated(false),

                Forms\Components\Textarea::make('note')
                    ->label('Ghi chú kế hoạch (AdOps)')
                    ->rows(5)
                    ->placeholder('Mô tả chi tiết kế hoạch triển khai...')
                    ->columnSpanFull(),

                Forms\Components\FileUpload::make('file_path')
                    ->label('File kế hoạch đính kèm')
                    ->directory('plans')
                    ->acceptedFileTypes([
                        'application/pdf',
                        'image/*',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/msword',
                    ])
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('plan_no')
            ->defaultSort('version', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('plan_no')
                    ->label('Mã Plan')
                    ->weight('bold')
                    ->url(fn (Plan $record) => PlanResource::getUrl('view', ['record' => $record])),

                Tables\Columns\TextColumn::make('version')
                    ->label('Ver.')
                    ->prefix('v')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('sale.name')
                    ->label('Sale')
                    ->getStateUsing(fn (Plan $record) => $record->brief?->sale?->name)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('budget')
                    ->label('Ngân sách')
                    ->money(fn (Plan $record) => $record->brief?->currency ?? 'VND')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Plan::$statuses[$state] ?? $state)
                    ->color(fn ($state) => Plan::$statusColors[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('responded_at')
                    ->label('Phản hồi lúc')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('create_plan')
                    ->label('+ Tạo Plan')
                    ->icon('heroicon-o-plus-circle')
                    ->color('primary')
                    ->url(fn () => PlanResource::getUrl('create', ['brief_id' => $this->getOwnerRecord()->id]))
                    ->visible(fn () => auth()->user()->hasAnyRole(['adops', 'ceo', 'coo'])
                        && in_array($this->getOwnerRecord()->status, [
                            'sent_to_adops', 'planning_ready', 'customer_feedback',
                        ])
                    ),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('view_plan_detail')
                        ->label('Xem chi tiết & Line items')
                        ->icon('heroicon-o-clipboard-document-list')
                        ->color('info')
                        ->url(fn (Plan $record) => PlanResource::getUrl('view', ['record' => $record])),

                    Tables\Actions\EditAction::make()
                        ->visible(fn (Plan $record) => $record->status === 'draft'
                            && $record->adops_id === auth()->id()
                        ),

                    // ── AdOps: Tạo plan điều chỉnh (đi đến trang tạo plan với data từ plan cũ) ──
                    Tables\Actions\Action::make('create_revision')
                        ->label('Tạo Plan điều chỉnh')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->visible(fn (Plan $record) => $record->status === 're_plan'
                            && auth()->user()->hasAnyRole(['adops', 'ceo', 'coo'])
                        )
                        ->url(fn (Plan $record) => PlanResource::getUrl('create', [
                            'brief_id'     => $record->brief_id,
                            'from_plan_id' => $record->id,
                        ])),

                    // ── AdOps: Gửi plan cho Sale duyệt ──────────────────────
                    Tables\Actions\Action::make('submit')
                        ->label('Gửi duyệt')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('info')
                        ->visible(fn (Plan $record) => $record->status === 'draft'
                            && $record->adops_id === auth()->id()
                        )
                        ->requiresConfirmation()
                        ->modalHeading('Xác nhận gửi Plan cho Sale review?')
                        ->action(function (Plan $record) {
                            $record->update(['status' => 'submitted']);
                            $record->brief->update(['status' => 'planning_ready']);

                            event(new PlanSubmitted(
                                subject: $record,
                                causer:  auth()->user(),
                                context: [
                                    'plan_no' => $record->plan_no,
                                    'version' => $record->version,
                                ]
                            ));

                            Notification::make()->title('Đã gửi Plan cho Sale review')->success()->send();
                        }),

                    // ── Sale: Accept plan ────────────────────────────────────
                    Tables\Actions\Action::make('accept')
                        ->label('Chấp nhận')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->visible(fn (Plan $record) => $record->status === 'submitted')
                        ->requiresConfirmation()
                        ->modalHeading('Xác nhận chấp nhận Plan này?')
                        ->modalDescription('Brief sẽ chuyển sang trạng thái "Confirmed" và có thể tạo Booking.')
                        ->action(function (Plan $record) {
                            $record->update([
                                'status'       => 'accepted',
                                'responded_by' => auth()->id(),
                                'responded_at' => now(),
                            ]);

                            $record->brief->plans()
                                ->where('id', '!=', $record->id)
                                ->whereNotIn('status', ['accepted', 'rejected'])
                                ->update(['status' => 're_plan']);

                            $record->brief->update(['status' => 'confirmed']);

                            event(new PlanAccepted(
                                subject: $record,
                                causer:  auth()->user(),
                                context: [
                                    'plan_no' => $record->plan_no,
                                    'version' => $record->version,
                                ]
                            ));

                            Notification::make()->title('Plan được chấp nhận — Brief đã confirmed')->success()->send();
                        }),

                    // ── Sale: Yêu cầu điều chỉnh ────────────────────────────
                    Tables\Actions\Action::make('re_plan')
                        ->label('Yêu cầu điều chỉnh')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->visible(fn (Plan $record) => $record->status === 'submitted')
                        ->form([
                            Forms\Components\Textarea::make('sale_comment')
                                ->label('Điểm cần trao đổi / điều chỉnh')
                                ->required()
                                ->rows(4)
                                ->placeholder('Mô tả các điểm AdOps cần điều chỉnh...'),
                        ])
                        ->modalHeading('Yêu cầu AdOps điều chỉnh Plan')
                        ->action(function (Plan $record, array $data) {
                            $record->update([
                                'status'       => 're_plan',
                                'sale_comment' => $data['sale_comment'],
                                'responded_by' => auth()->id(),
                                'responded_at' => now(),
                            ]);

                            $record->brief->update(['status' => 'sent_to_adops']);

                            event(new PlanRePlanRequested(
                                subject: $record,
                                causer:  auth()->user(),
                                context: [
                                    'plan_no' => $record->plan_no,
                                    'version' => $record->version,
                                    'comment' => $data['sale_comment'],
                                ]
                            ));

                            Notification::make()->title('Đã gửi yêu cầu điều chỉnh cho AdOps')->warning()->send();
                        }),

                    // ── Sale: Reject (đóng brief) ────────────────────────────
                    Tables\Actions\Action::make('reject')
                        ->label('Từ chối')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (Plan $record) => $record->status === 'submitted')
                        ->form([
                            Forms\Components\Textarea::make('sale_comment')
                                ->label('Lý do từ chối')
                                ->required()
                                ->rows(3),
                        ])
                        ->modalHeading('Từ chối Plan — Brief sẽ bị đóng')
                        ->modalDescription('Khách hàng từ chối. Brief sẽ chuyển trạng thái "Từ chối".')
                        ->action(function (Plan $record, array $data) {
                            $record->update([
                                'status'       => 'rejected',
                                'sale_comment' => $data['sale_comment'],
                                'responded_by' => auth()->id(),
                                'responded_at' => now(),
                            ]);

                            $record->brief->update(['status' => 'rejected']);

                            event(new PlanRejected(
                                subject: $record,
                                causer:  auth()->user(),
                                context: [
                                    'plan_no' => $record->plan_no,
                                    'version' => $record->version,
                                    'comment' => $data['sale_comment'],
                                ]
                            ));

                            Notification::make()->title('Brief đã bị từ chối và đóng lại')->danger()->send();
                        }),

                    // ── Download file ────────────────────────────────────────
                    Tables\Actions\Action::make('download')
                        ->label('Tải file')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('gray')
                        ->visible(fn (Plan $record) => (bool) $record->file_path)
                        ->url(fn (Plan $record) => Storage::url($record->file_path))
                        ->openUrlInNewTab(),
                ]),
            ]);
    }
}
