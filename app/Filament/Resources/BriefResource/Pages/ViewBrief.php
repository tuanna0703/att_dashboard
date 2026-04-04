<?php

namespace App\Filament\Resources\BriefResource\Pages;

use App\Events\Brief\BriefSentToAdops;
use App\Filament\Resources\BriefResource;
use App\Filament\Resources\BriefResource\RelationManagers\BriefLineItemsRelationManager;
use App\Models\Brief;
use App\Models\User;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components\Actions as InfolistActions;
use Filament\Infolists\Components\Actions\Action as InfolistAction;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\Tabs\Tab;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\View as InfolisView;
use Filament\Infolists\Infolist;
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
        return $this->record->campaign_name;
    }

    // ─── Header actions ───────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([

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

                        $adopsUser = User::find($data['adops_id']);

                        event(new BriefSentToAdops(
                            subject: $this->record,
                            causer:  auth()->user(),
                            context: [
                                'adops_id'   => $data['adops_id'],
                                'adops_name' => $adopsUser?->name ?? '—',
                            ]
                        ));

                        Notification::make()->title('Đã gửi Brief cho AdOps')->success()->send();
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

            ]),
        ];
    }

    // ─── Infolist ─────────────────────────────────────────────────────────────

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([

            // ── Tabs: General + Management ────────────────────────────────────
            Tabs::make()->tabs([

                Tab::make('General')
                    ->badge(fn () => Brief::$statuses[$this->record->status] ?? $this->record->status)
                    ->badgeColor(fn () => Brief::$statusColors[$this->record->status] ?? 'gray')
                    ->schema([
                    TextEntry::make('campaign_name')
                        ->label('Tên Campaign'),

                    TextEntry::make('brief_no')
                        ->label('Mã Brief'),

                    TextEntry::make('budget')
                        ->label('Budget')
                        ->weight('bold')
                        ->formatStateUsing(fn ($state, $record) => $state
                            ? number_format((float) $state, 0, ',', '.') . ' ' . ($record->currency ?? 'VND')
                            : '—'),

                    TextEntry::make('start_date')
                        ->label('Ngày bắt đầu')
                        ->getStateUsing(fn ($record) => $record->briefLineItems->min('start_date'))
                        ->date('d/m/Y')
                        ->placeholder('—'),

                    TextEntry::make('end_date')
                        ->label('Ngày kết thúc')
                        ->getStateUsing(fn ($record) => $record->briefLineItems->max('end_date'))
                        ->date('d/m/Y')
                        ->placeholder('—'),

                    TextEntry::make('customer.name')
                        ->label('Khách hàng'),
                ])->columns(3),

                Tab::make('Management')->schema([
                    TextEntry::make('sale.name')
                        ->label('Sale'),

                    TextEntry::make('adops.name')
                        ->label('AdOps')
                        ->placeholder('Chưa assign'),

                    TextEntry::make('note')
                        ->label('Ghi chú')
                        ->placeholder('—')
                        ->columnSpanFull(),

                    InfolistActions::make([
                        InfolistAction::make('download_file')
                            ->label('Download')
                            ->icon('heroicon-o-arrow-down-tray')
                            ->color('gray')
                            ->action(fn ($record) => Storage::disk('public')->download($record->file_path))
                            ->visible(fn ($record) => (bool) $record->file_path),
                    ])
                        ->label('File đính kèm')
                        ->columnSpanFull(),
                ])->columns(2),

            ])->columnSpanFull(),
        ]);
    }
}
