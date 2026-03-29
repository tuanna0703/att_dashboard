<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->label('Chỉnh sửa'),
            Actions\Action::make('view_contract')
                ->label('Xem hợp đồng')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->url(fn () => $this->record->contract_id
                    ? '/admin/contracts/' . $this->record->contract_id
                    : null)
                ->visible(fn () => (bool) $this->record->contract_id),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([

            Section::make('Tổng quan')->schema([
                TextEntry::make('invoice_no')
                    ->label('Số hóa đơn')
                    ->weight('bold')
                    ->copyable(),
                TextEntry::make('invoice_date')
                    ->label('Ngày xuất')
                    ->date('d/m/Y'),
                TextEntry::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'draft'          => 'Nháp',
                        'sent'           => 'Đã gửi KH',
                        'partially_paid' => 'Thu một phần',
                        'paid'           => 'Đã thu',
                        'cancelled'      => 'Huỷ',
                        default          => $state,
                    })
                    ->color(fn ($state) => match ($state) {
                        'draft'          => 'gray',
                        'sent'           => 'primary',
                        'partially_paid' => 'warning',
                        'paid'           => 'success',
                        'cancelled'      => 'danger',
                        default          => 'gray',
                    }),
                TextEntry::make('invoice_value')
                    ->label('Giá trị hóa đơn')
                    ->money('VND')
                    ->weight('bold'),
                TextEntry::make('vat_value')
                    ->label('Tiền VAT')
                    ->money('VND'),
                TextEntry::make('_total')
                    ->label('Tổng cộng (incl. VAT)')
                    ->state(fn ($record) => $record->totalValue())
                    ->money('VND')
                    ->weight('bold')
                    ->color('primary'),
            ])->columns(3),

            Section::make('Hợp đồng liên kết')->schema([
                TextEntry::make('contract.contract_code')
                    ->label('Số hợp đồng')
                    ->weight('bold'),
                TextEntry::make('contract.customer.name')
                    ->label('Khách hàng')
                    ->weight('bold'),
                TextEntry::make('contract.status')
                    ->label('Trạng thái HĐ')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'draft'     => 'Nháp',
                        'active'    => 'Đang chạy',
                        'completed' => 'Hoàn thành',
                        'cancelled' => 'Huỷ',
                        default     => $state,
                    })
                    ->color(fn ($state) => match ($state) {
                        'active'    => 'success',
                        'completed' => 'primary',
                        'cancelled' => 'danger',
                        default     => 'gray',
                    }),
            ])->columns(3),

            Section::make('Lịch thanh toán liên kết')->schema([
                TextEntry::make('_schedules_count')
                    ->label('Số đợt liên kết')
                    ->state(fn ($record) => $record->paymentSchedules()->count() . ' đợt'),
                TextEntry::make('_schedules_paid')
                    ->label('Đã thu')
                    ->state(fn ($record) => $record->paymentSchedules()->where('status', 'paid')->count() . ' đợt')
                    ->color('success'),
                TextEntry::make('_schedules_pending')
                    ->label('Chưa thu')
                    ->state(fn ($record) => $record->paymentSchedules()->whereNotIn('status', ['paid', 'cancelled'])->count() . ' đợt')
                    ->color('warning'),
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
