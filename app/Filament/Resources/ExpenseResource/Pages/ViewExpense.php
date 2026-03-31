<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Resources\ExpenseResource;
use App\Models\Expense;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Forms;

class ViewExpense extends ViewRecord
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('Chỉnh sửa')
                ->visible(fn () => in_array($this->record->status, ['draft', 'rejected'])),

            Actions\Action::make('submit')
                ->label('Gửi duyệt')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->visible(fn () => $this->record->status === 'draft')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update(['status' => 'pending']);
                    Notification::make()->title('Đã gửi phiếu chi để duyệt')->success()->send();
                    $this->refreshFormData(['status']);
                }),

            Actions\Action::make('approve')
                ->label('Duyệt')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->record->status === 'pending' && auth()->user()->hasPermissionTo('expenses.approve'))
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update([
                        'status'      => 'approved',
                        'approved_by' => auth()->id(),
                        'approved_at' => now(),
                    ]);
                    Notification::make()->title('Đã duyệt phiếu chi')->success()->send();
                    $this->refreshFormData(['status', 'approved_by', 'approved_at']);
                }),

            Actions\Action::make('reject')
                ->label('Từ chối')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => $this->record->status === 'pending' && auth()->user()->hasPermissionTo('expenses.approve'))
                ->form([
                    Forms\Components\Textarea::make('rejection_reason')
                        ->label('Lý do từ chối')
                        ->required()
                        ->rows(3),
                ])
                ->modalHeading('Từ chối phiếu chi')
                ->action(function (array $data) {
                    $this->record->update([
                        'status'           => 'rejected',
                        'rejection_reason' => $data['rejection_reason'],
                    ]);
                    Notification::make()->title('Đã từ chối phiếu chi')->danger()->send();
                    $this->refreshFormData(['status', 'rejection_reason']);
                }),

            Actions\Action::make('mark_paid')
                ->label('Đánh dấu đã chi')
                ->icon('heroicon-o-banknotes')
                ->color('primary')
                ->visible(fn () => $this->record->status === 'approved')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update(['status' => 'paid']);
                    Notification::make()->title('Đã cập nhật trạng thái Đã thanh toán')->success()->send();
                    $this->refreshFormData(['status']);
                }),

            Actions\Action::make('reset_draft')
                ->label('Chuyển về Nháp')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->visible(fn () => $this->record->status === 'rejected')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update(['status' => 'draft', 'rejection_reason' => null]);
                    Notification::make()->title('Đã chuyển về trạng thái Nháp')->success()->send();
                    $this->refreshFormData(['status', 'rejection_reason']);
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Thông tin phiếu chi')->schema([
                TextEntry::make('expense_no')->label('Mã phiếu chi')->weight('bold')->copyable(),
                TextEntry::make('expense_date')->label('Ngày chi')->date('d/m/Y'),
                TextEntry::make('category.name')->label('Danh mục')->placeholder('—'),
                TextEntry::make('contract.contract_code')->label('Hợp đồng')->placeholder('—'),
                TextEntry::make('vendor.name')->label('Nhà cung cấp')->placeholder('—'),
                TextEntry::make('total_amount')->label('Tổng tiền')->money('VND')->weight('bold'),
                TextEntry::make('payment_method')
                    ->label('Hình thức chi')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'bank_transfer' => 'Chuyển khoản',
                        'cash'          => 'Tiền mặt',
                        'cheque'        => 'Séc',
                        default         => $state,
                    })
                    ->color(fn ($state) => match ($state) {
                        'bank_transfer' => 'primary',
                        'cash'          => 'success',
                        'cheque'        => 'warning',
                        default         => 'gray',
                    }),
                TextEntry::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Expense::$statuses[$state] ?? $state)
                    ->color(fn ($state) => Expense::$statusColors[$state] ?? 'gray'),
                TextEntry::make('reference_no')->label('Số chứng từ')->placeholder('—'),
                TextEntry::make('recordedBy.name')->label('Người lập phiếu')->placeholder('—'),
                TextEntry::make('approvedBy.name')->label('Người duyệt')->placeholder('—'),
                TextEntry::make('approved_at')->label('Ngày duyệt')->dateTime('d/m/Y H:i')->placeholder('—'),
                TextEntry::make('rejection_reason')->label('Lý do từ chối')->placeholder('—')->columnSpanFull(),
                TextEntry::make('note')->label('Ghi chú')->placeholder('—')->columnSpanFull(),
            ])->columns(3),
        ]);
    }
}
