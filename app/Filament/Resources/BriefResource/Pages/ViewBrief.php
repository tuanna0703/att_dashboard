<?php

namespace App\Filament\Resources\BriefResource\Pages;

use App\Filament\Resources\BriefResource;
use App\Filament\Resources\BriefResource\RelationManagers\BriefLineItemsRelationManager;
use App\Models\Brief;
use App\Models\User;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components\Actions as InfolistActions;
use Filament\Infolists\Components\Actions\Action as InfolistAction;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\View as InfolisView;
use Filament\Infolists\Infolist;
use Filament\Notifications\Actions\Action as NotifAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Storage;

class ViewBrief extends ViewRecord
{
    protected static string $resource = BriefResource::class;

    public function getRelationManagers(): array
    {
        return [
            BriefLineItemsRelationManager::class,
            ...BriefResource::getRelationManagers(),
        ];
    }

    public function getTitle(): string
    {
        return $this->record->brief_no;
    }

    // ─── Header actions ───────────────────────────────────────────────────────

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
                        ->options(User::orderBy('name')->pluck('name', 'id'))
                        ->required()
                        ->searchable(),
                ])
                ->action(function (array $data) {
                    $this->record->update([
                        'status'   => 'sent_to_adops',
                        'adops_id' => $data['adops_id'],
                    ]);
                    Notification::make()->title('Đã gửi Brief cho AdOps')->success()->send();

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
                    $brief         = $this->record;
                    $acceptedPlan  = $brief->plans()->where('status', 'accepted')->latest()->first();
                    $finalRevision = $brief->revisions()->where('is_final', true)->first();
                    $source        = $acceptedPlan ?? $brief;
                    $lineItems     = $brief->briefLineItems;

                    $booking = \App\Models\Booking::create([
                        'brief_id'          => $brief->id,
                        'brief_revision_id' => $finalRevision?->id,
                        'plan_id'           => $acceptedPlan?->id,
                        'customer_id'       => $brief->customer_id,
                        'sale_id'           => $brief->sale_id,
                        'adops_id'          => $brief->adops_id,
                        'campaign_name'     => $source->campaign_name,
                        'start_date'        => $lineItems->min('start_date'),
                        'end_date'          => $lineItems->max('end_date'),
                        'total_budget'      => $source->budget,
                        'status'            => 'pending_contract',
                    ]);

                    $brief->update(['status' => 'converted']);
                    Notification::make()->title("Đã tạo Booking {$booking->booking_no}")->success()->send();
                    $this->refreshFormData(['status']);
                }),
        ];
    }

    // ─── Infolist ─────────────────────────────────────────────────────────────

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([

            // ── Hero: campaign name + customer + status ───────────────────────
            InfolisView::make('filament.infolists.brief-hero')
                ->viewData(['record' => $this->record])
                ->columnSpanFull(),

            // ── Stats: budget + timeline + duration ───────────────────────────
            InfolisView::make('filament.infolists.brief-stats')
                ->viewData(['record' => $this->record])
                ->columnSpanFull(),

            // ── Detail sections ───────────────────────────────────────────────
            Grid::make(2)->schema([
                Section::make('Thông tin brief')->schema([
                    TextEntry::make('brief_no')
                        ->label('Mã Brief'),

                    TextEntry::make('customer.name')
                        ->label('Khách hàng'),

                    TextEntry::make('status')
                        ->label('Trạng thái')
                        ->badge()
                        ->formatStateUsing(fn ($state) => Brief::$statuses[$state] ?? $state)
                        ->color(fn ($state) => Brief::$statusColors[$state] ?? 'gray'),

                    TextEntry::make('currency')
                        ->label('Loại tiền'),

                    TextEntry::make('created_at')
                        ->label('Ngày tạo')
                        ->dateTime('d/m/Y H:i')
                        ->columnSpan(2),
                ])->columns(2),

                Section::make('Người phụ trách')->schema([
                    TextEntry::make('sale.name')
                        ->label('Sale'),

                    TextEntry::make('adops.name')
                        ->label('AdOps')
                        ->placeholder('Chưa assign'),
                ])->columns(2),
            ])->columnSpanFull(),

            // ── Ghi chú ──────────────────────────────────────────────────────
            Section::make('Ghi chú')->schema([
                TextEntry::make('note')
                    ->hiddenLabel()
                    ->columnSpanFull(),
            ])
                ->visible(fn ($record) => (bool) $record->note)
                ->columnSpanFull(),

            // ── File đính kèm ─────────────────────────────────────────────────
            Section::make('File đính kèm')->schema([
                InfolistActions::make([
                    InfolistAction::make('download_file')
                        ->label(fn ($record) => basename($record->file_path))
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('gray')
                        ->url(fn ($record) => Storage::url($record->file_path))
                        ->openUrlInNewTab(),
                ])->hiddenLabel(),
            ])
                ->visible(fn ($record) => (bool) $record->file_path)
                ->columnSpanFull(),
        ]);
    }
}
