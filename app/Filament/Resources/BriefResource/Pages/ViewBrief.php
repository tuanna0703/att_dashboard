<?php

namespace App\Filament\Resources\BriefResource\Pages;

use App\Filament\Resources\BriefResource;
use App\Filament\Resources\BriefResource\RelationManagers\BriefLineItemsRelationManager;
use App\Models\User;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components\View as InfolisView;
use Filament\Infolists\Infolist;
use Filament\Notifications\Actions\Action as NotifAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

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

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            InfolisView::make('filament.infolists.brief-campaign-info')
                ->viewData(['record' => $this->record]),
        ]);
    }
}
