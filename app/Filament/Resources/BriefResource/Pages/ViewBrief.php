<?php

namespace App\Filament\Resources\BriefResource\Pages;

use App\Filament\Resources\BriefResource;
use App\Models\Brief;
use App\Models\User;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components\Grid;
use Filament\Notifications\Actions\Action as NotifAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewBrief extends ViewRecord
{
    protected static string $resource = BriefResource::class;

    public function getRelationManagers(): array
    {
        return BriefResource::getRelationManagers();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn () => in_array($this->record->status, ['draft', 'customer_feedback'])),

            Actions\Action::make('send_to_adops')
                ->label('Gửi AdOps')
                ->icon('heroicon-o-arrow-right-circle')
                ->color('info')
                ->visible(fn () => $this->record->status === 'draft')
                ->form([
                    Forms\Components\Select::make('adops_id')
                        ->label('Assign cho AdOps')
                        ->options(\App\Models\User::orderBy('name')->pluck('name', 'id'))
                        ->required()
                        ->searchable(),
                ])
                ->action(function (array $data) {
                    $this->record->update([
                        'status'   => 'sent_to_adops',
                        'adops_id' => $data['adops_id'],
                    ]);
                    Notification::make()->title('Đã gửi Brief cho AdOps')->success()->send();

                    // Gửi DB notification cho AdOps
                    $adopsUser = User::find($data['adops_id']);
                    if ($adopsUser) {
                        Notification::make()
                            ->title('Brief mới được assign cho bạn')
                            ->body("{$this->record->brief_no} — {$this->record->campaign_name}")
                            ->icon('heroicon-o-document-magnifying-glass')
                            ->iconColor('info')
                            ->actions([
                                NotifAction::make('view')
                                    ->label('Xem Brief')
                                    ->url(BriefResource::getUrl('view', ['record' => $this->record])),
                            ])
                            ->sendToDatabase($adopsUser);
                    }

                    $this->refreshFormData(['status', 'adops_id']);
                }),

            Actions\Action::make('convert_to_booking')
                ->label('Tạo Booking')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('primary')
                ->visible(fn () => $this->record->status === 'confirmed')
                ->requiresConfirmation()
                ->modalHeading('Chuyển Brief thành Booking?')
                ->modalDescription('Revision is_final sẽ được áp dụng cho Booking.')
                ->action(function () {
                    $brief        = $this->record;
                    $acceptedPlan = $brief->plans()->where('status', 'accepted')->latest()->first();
                    $finalRevision = $brief->revisions()->where('is_final', true)->first();

                    // Lấy data từ accepted Plan nếu có, fallback về Brief
                    $source = $acceptedPlan ?? $brief;

                    $booking = \App\Models\Booking::create([
                        'brief_id'          => $brief->id,
                        'brief_revision_id' => $finalRevision?->id,
                        'plan_id'           => $acceptedPlan?->id,
                        'customer_id'       => $brief->customer_id,
                        'sale_id'           => $brief->sale_id,
                        'adops_id'          => $brief->adops_id,
                        'campaign_name'     => $source->campaign_name,
                        'start_date'        => $source->start_date,
                        'end_date'          => $source->end_date,
                        'total_budget'      => $source->budget,
                        'status'            => 'pending_contract',
                    ]);

                    $brief->update(['status' => 'converted']);
                    Notification::make()->title("Đã tạo Booking {$booking->booking_no}")->success()->send();
                    $this->refreshFormData(['status']);
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Thông tin Campaign')->schema([
                TextEntry::make('brief_no')->label('Mã Brief')->weight('bold')->copyable(),
                TextEntry::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Brief::$statuses[$state] ?? $state)
                    ->color(fn ($state) => Brief::$statusColors[$state] ?? 'gray'),
                TextEntry::make('campaign_name')->label('Tên Campaign')->columnSpan(2),
                TextEntry::make('customer.name')->label('Khách hàng'),
                TextEntry::make('sale.name')->label('Sale'),
                TextEntry::make('adops.name')->label('AdOps')->placeholder('Chưa assign'),
                TextEntry::make('start_date')->label('Bắt đầu')->date('d/m/Y'),
                TextEntry::make('end_date')->label('Kết thúc')->date('d/m/Y'),
                TextEntry::make('budget')->label('Ngân sách')->money('VND')->placeholder('—'),
                TextEntry::make('cpm')->label('CPM')->money('VND')->placeholder('—'),
                TextEntry::make('note')->label('Ghi chú')->placeholder('—')->columnSpanFull(),
                TextEntry::make('file_path')
                    ->label('File đính kèm')
                    ->formatStateUsing(fn ($state) => $state ? basename($state) : null)
                    ->placeholder('Không có file')
                    ->url(fn ($state) => $state ? Storage::url($state) : null)
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-paper-clip')
                    ->columnSpanFull(),
            ])->columns(4),

            Section::make('Mạng lưới quảng cáo')->schema([
                RepeatableEntry::make('briefAdNetworks')
                    ->label('')
                    ->schema([
                        TextEntry::make('adNetwork.name')->label('Mạng lưới'),
                        TextEntry::make('screen_count')->label('Số màn hình')->placeholder('—'),
                        TextEntry::make('note')->label('Ghi chú')->placeholder('—'),
                    ])
                    ->columns(3),
            ]),
        ]);
    }
}
