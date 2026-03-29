<?php

namespace App\Filament\Resources\ContractResource\Pages;

use App\Filament\Resources\ContractResource;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewContract extends ViewRecord
{
    protected static string $resource = ContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->label('Chỉnh sửa'),
            Actions\Action::make('payment_schedules')
                ->label('Lịch thanh toán')
                ->icon('heroicon-o-calendar-days')
                ->color('gray')
                ->url(fn (): string => '/admin/payment-schedules?tableFilters[contract][value]=' . $this->record->id),
            Actions\Action::make('invoices')
                ->label('Hóa đơn')
                ->icon('heroicon-o-receipt-percent')
                ->color('gray')
                ->url(fn (): string => '/admin/invoices?tableFilters[contract][value]=' . $this->record->id),
            Actions\Action::make('receipts')
                ->label('Phiếu thu')
                ->icon('heroicon-o-banknotes')
                ->color('gray')
                ->url(fn (): string => '/admin/receipts?tableFilters[contract][value]=' . $this->record->id),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([

            // ── Tổng quan tài chính ─────────────────────────────────────────
            Section::make('Tổng quan tài chính')->schema([
                TextEntry::make('total_value_estimated')
                    ->label('Giá trị HĐ (est.)')
                    ->money('VND')
                    ->weight('bold'),
                TextEntry::make('_total_paid')
                    ->label('Đã thu')
                    ->state(fn ($record) => $record->totalPaid())
                    ->money('VND')
                    ->color('success')
                    ->weight('bold'),
                TextEntry::make('_total_outstanding')
                    ->label('Còn phải thu (AR)')
                    ->state(fn ($record) => $record->totalOutstanding())
                    ->money('VND')
                    ->color('warning')
                    ->weight('bold'),
                TextEntry::make('_total_overdue')
                    ->label('Quá hạn')
                    ->state(fn ($record) => $record->totalOverdue())
                    ->money('VND')
                    ->color(fn ($record) => $record->totalOverdue() > 0 ? 'danger' : 'gray')
                    ->weight('bold'),
                TextEntry::make('_schedule_count')
                    ->label('Số đợt thanh toán')
                    ->state(fn ($record) => $record->paymentSchedules()->count() . ' đợt'),
                TextEntry::make('_overdue_count')
                    ->label('Đợt quá hạn')
                    ->state(fn ($record) => $record->paymentSchedules()->where('status', 'overdue')->count() . ' đợt')
                    ->color(fn ($record) => $record->paymentSchedules()->where('status', 'overdue')->count() > 0 ? 'danger' : 'gray'),
                TextEntry::make('_invoice_count')
                    ->label('Số hóa đơn')
                    ->state(fn ($record) => $record->invoices()->count() . ' hóa đơn'),
                TextEntry::make('_invoice_value')
                    ->label('Tổng giá trị HĐ xuất')
                    ->state(fn ($record) => $record->invoices()->sum('invoice_value'))
                    ->money('VND'),
            ])->columns(4),

            // ── Thông tin hợp đồng ──────────────────────────────────────────
            Section::make('Thông tin hợp đồng')->schema([
                TextEntry::make('contract_code')
                    ->label('Số hợp đồng')
                    ->weight('bold')
                    ->copyable(),
                TextEntry::make('name')
                    ->label('Tên hợp đồng')
                    ->placeholder('—'),
                TextEntry::make('customer.name')
                    ->label('Khách hàng')
                    ->weight('bold'),
                TextEntry::make('contract_type')
                    ->label('Loại hợp đồng')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'ads'          => 'Quảng cáo',
                        'project'      => 'Dự án',
                        'subscription' => 'Thuê bao',
                        default        => $state,
                    })
                    ->color(fn ($state) => match ($state) {
                        'ads'          => 'warning',
                        'project'      => 'primary',
                        'subscription' => 'success',
                        default        => 'gray',
                    }),
                TextEntry::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'draft'     => 'Nháp',
                        'active'    => 'Đang chạy',
                        'completed' => 'Hoàn thành',
                        'cancelled' => 'Huỷ',
                        default     => $state,
                    })
                    ->color(fn ($state) => match ($state) {
                        'draft'     => 'gray',
                        'active'    => 'success',
                        'completed' => 'primary',
                        'cancelled' => 'danger',
                        default     => 'gray',
                    }),
                TextEntry::make('currency')->label('Tiền tệ'),
                TextEntry::make('signed_date')->label('Ngày ký')->date('d/m/Y')->placeholder('—'),
                TextEntry::make('start_date')->label('Ngày bắt đầu')->date('d/m/Y')->placeholder('—'),
                TextEntry::make('end_date')
                    ->label('Ngày kết thúc')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->color(fn ($record) => $record?->end_date?->isPast() && $record->status === 'active' ? 'danger' : null),
            ])->columns(3),

            // ── Phân công ───────────────────────────────────────────────────
            Section::make('Phân công')->schema([
                TextEntry::make('saleOwner.name')->label('Sale phụ trách')->placeholder('Chưa phân công'),
                TextEntry::make('accountOwner.name')->label('Account phụ trách')->placeholder('Chưa phân công'),
                TextEntry::make('financeOwner.name')->label('Finance phụ trách')->placeholder('Chưa phân công'),
            ])->columns(3),

            // ── Ghi chú ─────────────────────────────────────────────────────
            Section::make()->schema([
                TextEntry::make('note')->label('Ghi chú')->placeholder('Không có ghi chú')->columnSpanFull(),
            ]),
        ]);
    }
}
