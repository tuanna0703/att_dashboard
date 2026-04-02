<?php

namespace App\Filament\Resources\BriefResource\RelationManagers;

use App\Filament\Resources\BriefResource;
use App\Filament\Resources\PlanResource;
use App\Models\Plan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Actions\Action as NotifAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\RawJs;
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
        $brief = $this->getOwnerRecord();

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

                Forms\Components\TextInput::make('campaign_name')
                    ->label('Tên campaign')
                    ->required()
                    ->default($brief->campaign_name)
                    ->maxLength(200)
                    ->columnSpan(2),

                Forms\Components\DatePicker::make('start_date')
                    ->label('Ngày bắt đầu')
                    ->default($brief->start_date)
                    ->displayFormat('d/m/Y'),

                Forms\Components\DatePicker::make('end_date')
                    ->label('Ngày kết thúc')
                    ->default($brief->end_date)
                    ->displayFormat('d/m/Y')
                    ->after('start_date'),

                Forms\Components\TextInput::make('budget')
                    ->label('Ngân sách (VND)')
                    ->prefix('₫')
                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                    ->dehydrateStateUsing(fn ($state) => $state ? (float) str_replace('.', '', (string) $state) : null)
                    ->afterStateHydrated(function ($component, $state) {
                        if ($state !== null && $state !== '') {
                            $component->state(number_format((float) $state, 0, ',', '.'));
                        }
                    })
                    ->default($brief->budget ? number_format((float) $brief->budget, 0, ',', '.') : null),

                Forms\Components\TextInput::make('cpm')
                    ->label('CPM (VND)')
                    ->prefix('₫')
                    ->numeric()
                    ->default($brief->cpm),

                Forms\Components\TextInput::make('screen_count')
                    ->label('Số màn hình')
                    ->numeric()
                    ->minValue(1),

                Forms\Components\TextInput::make('duration_days')
                    ->label('Số ngày chạy')
                    ->numeric()
                    ->minValue(1),

            ])->columns(2),

            Forms\Components\Section::make('Nội dung kế hoạch')->schema([

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

            ]),
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
                    ->prefix(''),

                Tables\Columns\TextColumn::make('version')
                    ->label('Ver.')
                    ->prefix('v')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('AdOps')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Bắt đầu')
                    ->date('d/m/Y')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('end_date')
                    ->label('Kết thúc')
                    ->date('d/m/Y')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('budget')
                    ->label('Ngân sách')
                    ->money('VND')
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('file_path')
                    ->label('File')
                    ->boolean()
                    ->trueIcon('heroicon-o-paper-clip')
                    ->falseIcon('heroicon-o-minus')
                    ->getStateUsing(fn ($record) => (bool) $record->file_path),

                Tables\Columns\TextColumn::make('sale_comment')
                    ->label('Comment của Sale')
                    ->limit(40)
                    ->placeholder('—')
                    ->tooltip(fn ($record) => $record->sale_comment),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Trạng thái')
                    ->formatStateUsing(fn ($state) => Plan::$statuses[$state] ?? $state)
                    ->colors(Plan::$statusColors),

                Tables\Columns\TextColumn::make('responded_at')
                    ->label('Phản hồi lúc')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),
            ])
            ->headerActions([
                // AdOps tạo plan mới khi brief đang sent_to_adops hoặc cần re-plan
                Tables\Actions\CreateAction::make()
                    ->label('+ Tạo Plan')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['created_by'] = auth()->id();
                        $data['status']     = 'draft';
                        return $data;
                    })
                    ->visible(fn () => auth()->user()->hasAnyRole(['adops', 'ceo', 'coo'])
                        && in_array($this->getOwnerRecord()->status, [
                            'sent_to_adops', 'planning_ready', 'customer_feedback',
                        ])
                    ),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),

                    Tables\Actions\Action::make('view_plan_detail')
                        ->label('Xem chi tiết & Line items')
                        ->icon('heroicon-o-clipboard-document-list')
                        ->color('info')
                        ->url(fn (Plan $record) => PlanResource::getUrl('view', ['record' => $record])),

                    Tables\Actions\EditAction::make()
                        ->visible(fn (Plan $record) => $record->status === 'draft'),

                    // ── AdOps: Tạo plan điều chỉnh từ plan bị re_plan ────────
                    Tables\Actions\Action::make('create_revision')
                        ->label('Tạo Plan điều chỉnh')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->visible(fn (Plan $record) => $record->status === 're_plan'
                            && auth()->user()->hasAnyRole(['adops', 'ceo', 'coo'])
                        )
                        ->form([
                            Forms\Components\Grid::make(2)->schema([
                                Forms\Components\TextInput::make('campaign_name')
                                    ->label('Tên campaign')
                                    ->required()
                                    ->maxLength(200)
                                    ->columnSpan(2),

                                Forms\Components\DatePicker::make('start_date')
                                    ->label('Ngày bắt đầu')
                                    ->displayFormat('d/m/Y'),

                                Forms\Components\DatePicker::make('end_date')
                                    ->label('Ngày kết thúc')
                                    ->displayFormat('d/m/Y')
                                    ->afterOrEqual('start_date'),

                                Forms\Components\TextInput::make('budget')
                                    ->label('Ngân sách (VND)')
                                    ->prefix('₫')
                                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                                    ->dehydrateStateUsing(fn ($state) => $state ? (float) str_replace('.', '', (string) $state) : null)
                                    ->afterStateHydrated(function ($component, $state) {
                                        if ($state !== null && $state !== '') {
                                            $component->state(number_format((float) $state, 0, ',', '.'));
                                        }
                                    }),

                                Forms\Components\TextInput::make('cpm')
                                    ->label('CPM (VND)')
                                    ->prefix('₫')
                                    ->numeric(),

                                Forms\Components\TextInput::make('screen_count')
                                    ->label('Số màn hình')
                                    ->numeric()
                                    ->minValue(1),

                                Forms\Components\TextInput::make('duration_days')
                                    ->label('Số ngày chạy')
                                    ->numeric()
                                    ->minValue(1),

                                Forms\Components\Textarea::make('note')
                                    ->label('Ghi chú / Điều chỉnh so với plan cũ')
                                    ->rows(4)
                                    ->columnSpan(2),

                                Forms\Components\FileUpload::make('file_path')
                                    ->label('File kế hoạch mới')
                                    ->directory('plans')
                                    ->acceptedFileTypes([
                                        'application/pdf', 'image/*',
                                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                        'application/vnd.ms-excel',
                                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                        'application/msword',
                                    ])
                                    ->columnSpan(2),
                            ]),
                        ])
                        ->modalHeading('Tạo Plan điều chỉnh')
                        ->modalDescription(fn (Plan $record) => "Tạo phiên bản mới dựa trên Plan {$record->plan_no}. Lý do điều chỉnh: {$record->sale_comment}")
                        ->fillForm(fn (Plan $record) => [
                            'campaign_name' => $record->campaign_name,
                            'start_date'    => $record->start_date,
                            'end_date'      => $record->end_date,
                            'budget'        => $record->budget,
                            'cpm'           => $record->cpm,
                            'screen_count'  => $record->screen_count,
                            'duration_days' => $record->duration_days,
                            'note'          => $record->note,
                        ])
                        ->action(function (Plan $record, array $data) {
                            Plan::create([
                                'brief_id'      => $record->brief_id,
                                'campaign_name' => $data['campaign_name'],
                                'start_date'    => $data['start_date'] ?? null,
                                'end_date'      => $data['end_date'] ?? null,
                                'budget'        => $data['budget'] ?? null,
                                'cpm'           => $data['cpm'] ?? null,
                                'screen_count'  => $data['screen_count'] ?? null,
                                'duration_days' => $data['duration_days'] ?? null,
                                'note'          => $data['note'] ?? null,
                                'file_path'     => $data['file_path'] ?? null,
                                'status'        => 'draft',
                                'created_by'    => auth()->id(),
                            ]);

                            Notification::make()->title('Đã tạo Plan điều chỉnh — tiếp tục chỉnh sửa và gửi duyệt')->success()->send();
                        }),

                    // ── AdOps: Gửi plan cho Sale duyệt ──────────────────────
                    Tables\Actions\Action::make('submit')
                        ->label('Gửi duyệt')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('info')
                        ->visible(fn (Plan $record) => $record->status === 'draft' && $record->created_by === auth()->id())
                        ->requiresConfirmation()
                        ->modalHeading('Xác nhận gửi Plan cho Sale review?')
                        ->action(function (Plan $record) {
                            $record->update(['status' => 'submitted']);
                            $record->brief->update(['status' => 'planning_ready']);

                            // Notify người tạo brief (Sale)
                            $saleUser = $record->brief->sale;
                            if ($saleUser) {
                                Notification::make()
                                    ->title('Plan mới cho Brief của bạn')
                                    ->body("{$record->plan_no} (v{$record->version}) — {$record->brief->brief_no}: {$record->campaign_name}")
                                    ->icon('heroicon-o-clipboard-document-check')
                                    ->iconColor('info')
                                    ->actions([
                                        NotifAction::make('view')
                                            ->label('Xem Brief')
                                            ->url(BriefResource::getUrl('view', ['record' => $record->brief_id])),
                                    ])
                                    ->sendToDatabase($saleUser);
                            }

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
                                'status'        => 'accepted',
                                'responded_by'  => auth()->id(),
                                'responded_at'  => now(),
                            ]);

                            // Các plan khác của brief này → superseded
                            $record->brief->plans()
                                ->where('id', '!=', $record->id)
                                ->whereNotIn('status', ['accepted', 'rejected'])
                                ->update(['status' => 're_plan']);

                            $record->brief->update(['status' => 'confirmed']);

                            // Notify AdOps
                            $adops = $record->createdBy;
                            if ($adops) {
                                Notification::make()
                                    ->title('Plan của bạn đã được chấp nhận!')
                                    ->body("{$record->plan_no} — {$record->campaign_name}")
                                    ->icon('heroicon-o-check-badge')
                                    ->iconColor('success')
                                    ->actions([
                                        NotifAction::make('view')
                                            ->label('Xem Brief')
                                            ->url(BriefResource::getUrl('view', ['record' => $record->brief_id])),
                                    ])
                                    ->sendToDatabase($adops);
                            }

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

                            // Notify AdOps
                            $adops = $record->createdBy;
                            if ($adops) {
                                Notification::make()
                                    ->title('Plan cần điều chỉnh')
                                    ->body("{$record->plan_no}: {$data['sale_comment']}")
                                    ->icon('heroicon-o-arrow-path')
                                    ->iconColor('warning')
                                    ->actions([
                                        NotifAction::make('view')
                                            ->label('Xem Brief')
                                            ->url(BriefResource::getUrl('view', ['record' => $record->brief_id])),
                                    ])
                                    ->sendToDatabase($adops);
                            }

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

                            // Notify AdOps
                            $adops = $record->createdBy;
                            if ($adops) {
                                Notification::make()
                                    ->title('Plan bị từ chối')
                                    ->body("{$record->plan_no}: {$data['sale_comment']}")
                                    ->icon('heroicon-o-x-circle')
                                    ->iconColor('danger')
                                    ->actions([
                                        NotifAction::make('view')
                                            ->label('Xem Brief')
                                            ->url(BriefResource::getUrl('view', ['record' => $record->brief_id])),
                                    ])
                                    ->sendToDatabase($adops);
                            }

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
