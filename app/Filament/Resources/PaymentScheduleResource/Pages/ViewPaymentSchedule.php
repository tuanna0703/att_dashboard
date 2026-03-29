<?php

namespace App\Filament\Resources\PaymentScheduleResource\Pages;

use App\Filament\Resources\PaymentScheduleResource;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewPaymentSchedule extends ViewRecord
{
    protected static string $resource = PaymentScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->label('Chỉnh sửa'),
            Actions\Action::make('view_contract')
                ->label('Xem hợp đồng')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->url(fn () => '/admin/contracts/' . $this->record->contract_id)
                ->visible(fn () => (bool) $this->record->contract_id),
            Actions\Action::make('view_invoice')
                ->label('Xem hóa đơn')
                ->icon('heroicon-o-receipt-percent')
                ->color('gray')
                ->url(fn () => '/admin/invoices/' . $this->record->invoice_id)
                ->visible(fn () => (bool) $this->record->invoice_id),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([

            Section::make('Tổng quan thanh toán')->schema([
                TextEntry::make('_installment')
                    ->label('Đợt thanh toán')
                    ->state(fn ($record) => 'Đợt ' . $record->installment_no)
                    ->weight('bold'),
                TextEntry::make('schedule_type')
                    ->label('Loại đợt')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'advance'      => 'Tạm ứng',
                        'milestone'    => 'Milestone',
                        'acceptance'   => 'Nghiệm thu',
                        'subscription' => 'Thuê bao',
                        default        => $state,
                    })
                    ->color(fn ($state) => match ($state) {
                        'advance'      => 'warning',
                        'milestone'    => 'primary',
                        'acceptance'   => 'success',
                        'subscription' => 'info',
                        default        => 'gray',
                    }),
                TextEntry::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'pending'        => 'Chờ xử lý',
                        'invoiced'       => 'Đã xuất HĐ',
                        'partially_paid' => 'Thu một phần',
                        'paid'           => 'Đã thu',
                        'overdue'        => 'Quá hạn',
                        'cancelled'      => 'Huỷ',
                        default          => $state,
                    })
                    ->color(fn ($state) => match ($state) {
                        'pending'        => 'gray',
                        'invoiced'       => 'primary',
                        'partially_paid' => 'warning',
                        'paid'           => 'success',
                        'overdue'        => 'danger',
                        'cancelled'      => 'danger',
                        default          => 'gray',
                    }),
                TextEntry::make('amount')
                    ->label('Số tiền')
                    ->money('VND')
                    ->weight('bold'),
                TextEntry::make('vat_amount')
                    ->label('Tiền VAT')
                    ->money('VND'),
                TextEntry::make('_total_with_vat')
                    ->label('Tổng (incl. VAT)')
                    ->state(fn ($record) => $record->totalWithVat())
                    ->money('VND')
                    ->weight('bold')
                    ->color('primary'),
            ])->columns(3),

            Section::make('Tiến độ thu')->schema([
                TextEntry::make('_received')
                    ->label('Đã thu')
                    ->state(fn ($record) => $record->amountReceived())
                    ->money('VND')
                    ->color('success')
                    ->weight('bold'),
                TextEntry::make('_remaining')
                    ->label('Còn lại')
                    ->state(fn ($record) => $record->amountRemaining())
                    ->money('VND')
                    ->color(fn ($record) => $record->amountRemaining() > 0 ? 'warning' : 'success')
                    ->weight('bold'),
                TextEntry::make('_allocations_count')
                    ->label('Số phiếu thu')
                    ->state(fn ($record) => $record->receiptAllocations()->count() . ' phiếu'),
            ])->columns(3),

            Section::make('Thời hạn')->schema([
                TextEntry::make('due_date')
                    ->label('Hạn thanh toán')
                    ->date('d/m/Y')
                    ->weight('bold')
                    ->color(fn ($record) => $record->isOverdue() ? 'danger' : null),
                TextEntry::make('invoice_expected_date')
                    ->label('Dự kiến xuất HĐ')
                    ->date('d/m/Y')
                    ->placeholder('—'),
                TextEntry::make('invoice_issued_date')
                    ->label('Ngày xuất HĐ thực tế')
                    ->date('d/m/Y')
                    ->placeholder('—'),
            ])->columns(3),

            Section::make('Hợp đồng & Phụ trách')->schema([
                TextEntry::make('contract.contract_code')
                    ->label('Số hợp đồng')
                    ->weight('bold'),
                TextEntry::make('contract.customer.name')
                    ->label('Khách hàng'),
                TextEntry::make('responsibleUser.name')
                    ->label('Người phụ trách')
                    ->placeholder('Chưa phân công'),
            ])->columns(3),

            Section::make()->schema([
                TextEntry::make('note')
                    ->label('Ghi chú')
                    ->placeholder('Không có ghi chú')
                    ->columnSpanFull(),
            ]),
        ]);
    }
}
