<?php

namespace App\Filament\Resources\ContractResource\Pages;

use App\Filament\Resources\ContractResource;
use App\Filament\Resources\ReceiptResource;
use App\Models\CustomerContact;
use App\Models\Invoice;
use App\Models\PaymentSchedule;
use App\Models\Receipt;
use App\Models\User;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\Tabs\Tab;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewContract extends ViewRecord
{
    protected static string $resource = ContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create_payment_schedule')
                ->label('Thêm lịch thanh toán')
                ->icon('heroicon-o-calendar-days')
                ->color('primary')
                ->modalWidth('2xl')
                ->modalHeading('Thêm lịch thanh toán')
                ->form([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('installment_no')
                            ->label('Đợt số')
                            ->numeric()
                            ->required()
                            ->default(fn () => $this->record->paymentSchedules()->max('installment_no') + 1),
                        Forms\Components\Select::make('schedule_type')
                            ->label('Loại đợt')
                            ->options([
                                'advance'      => 'Tạm ứng',
                                'milestone'    => 'Milestone',
                                'acceptance'   => 'Nghiệm thu',
                                'subscription' => 'Thuê bao',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('amount')
                            ->label('Số tiền')
                            ->numeric()
                            ->prefix('VND')
                            ->required(),
                        Forms\Components\TextInput::make('vat_amount')
                            ->label('Tiền VAT')
                            ->numeric()
                            ->prefix('VND')
                            ->default(0),
                        Forms\Components\DatePicker::make('due_date')
                            ->label('Hạn thanh toán')
                            ->required()
                            ->displayFormat('d/m/Y'),
                        Forms\Components\DatePicker::make('invoice_expected_date')
                            ->label('Dự kiến xuất HĐ')
                            ->displayFormat('d/m/Y'),
                        Forms\Components\Select::make('responsible_user_id')
                            ->label('Người phụ trách')
                            ->options(User::pluck('name', 'id'))
                            ->searchable()
                            ->default($this->record->finance_owner_id),
                        Forms\Components\Select::make('status')
                            ->label('Trạng thái')
                            ->options([
                                'pending'  => 'Chờ xử lý',
                                'invoiced' => 'Đã xuất HĐ',
                            ])
                            ->default('pending')
                            ->required(),
                        Forms\Components\Textarea::make('note')
                            ->label('Ghi chú')
                            ->columnSpanFull(),
                    ]),
                ])
                ->action(function (array $data): void {
                    $this->record->paymentSchedules()->create($data);
                    Notification::make()->title('Đã thêm lịch thanh toán')->success()->send();
                }),

            Actions\Action::make('create_invoice')
                ->label('Thêm hóa đơn')
                ->icon('heroicon-o-receipt-percent')
                ->color('warning')
                ->modalWidth('2xl')
                ->modalHeading('Thêm hóa đơn')
                ->form([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('invoice_no')
                            ->label('Số hóa đơn')
                            ->required()
                            ->unique('invoices', 'invoice_no'),
                        Forms\Components\DatePicker::make('invoice_date')
                            ->label('Ngày xuất hóa đơn')
                            ->required()
                            ->default(now())
                            ->displayFormat('d/m/Y'),
                        Forms\Components\TextInput::make('invoice_value')
                            ->label('Giá trị HĐ')
                            ->numeric()
                            ->prefix('VND')
                            ->required(),
                        Forms\Components\TextInput::make('vat_value')
                            ->label('Tiền VAT')
                            ->numeric()
                            ->prefix('VND')
                            ->default(0),
                        Forms\Components\Select::make('status')
                            ->label('Trạng thái')
                            ->options([
                                'draft' => 'Nháp',
                                'sent'  => 'Đã gửi KH',
                            ])
                            ->default('draft')
                            ->required(),
                        Forms\Components\Textarea::make('note')
                            ->label('Ghi chú')
                            ->columnSpanFull(),
                    ]),
                ])
                ->action(function (array $data): void {
                    $this->record->invoices()->create($data);
                    Notification::make()->title('Đã thêm hóa đơn')->success()->send();
                }),

            Actions\Action::make('create_receipt')
                ->label('Thêm phiếu thu')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->url(fn () => ReceiptResource::getUrl('create')),

            Actions\Action::make('create_contact')
                ->label('Thêm người liên hệ')
                ->icon('heroicon-o-user-plus')
                ->color('gray')
                ->modalWidth('2xl')
                ->modalHeading('Thêm người liên hệ')
                ->form([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Họ và tên')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('title')
                            ->label('Chức danh')
                            ->placeholder('Giám đốc, Trưởng phòng...')
                            ->maxLength(100),
                        Forms\Components\Select::make('role')
                            ->label('Vai trò')
                            ->options(CustomerContact::$roles)
                            ->required()
                            ->default('other'),
                        Forms\Components\Toggle::make('is_primary')
                            ->label('Người liên hệ chính')
                            ->default(false),
                        Forms\Components\TextInput::make('phone')
                            ->label('Số điện thoại')
                            ->tel()
                            ->maxLength(20),
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('note')
                            ->label('Ghi chú')
                            ->columnSpanFull(),
                    ]),
                ])
                ->action(function (array $data): void {
                    $this->record->customer->contacts()->create($data);
                    Notification::make()
                        ->title('Đã thêm người liên hệ cho ' . $this->record->customer->name)
                        ->success()
                        ->send();
                }),

            Actions\EditAction::make()->label('Chỉnh sửa'),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Tabs::make('contract_tabs')->tabs([

                Tab::make('Tổng quan tài chính')
                    ->icon('heroicon-o-chart-bar')
                    ->schema([
                        Section::make('Doanh thu')->schema([
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

                        Section::make('Chi phí & Lợi nhuận')->schema([
                            TextEntry::make('_total_expense')
                                ->label('Tổng chi phí')
                                ->state(fn ($record) => (float) $record->expenses()->whereIn('status', ['approved', 'paid'])->sum('total_amount'))
                                ->money('VND')
                                ->color('danger')
                                ->weight('bold'),
                            TextEntry::make('_expense_paid')
                                ->label('Đã thanh toán chi phí')
                                ->state(fn ($record) => (float) $record->expenses()->where('status', 'paid')->sum('total_amount'))
                                ->money('VND')
                                ->color('danger'),
                            TextEntry::make('_expense_pending')
                                ->label('Chi phí chờ duyệt')
                                ->state(fn ($record) => (float) $record->expenses()->where('status', 'pending')->sum('total_amount'))
                                ->money('VND')
                                ->color('warning'),
                            TextEntry::make('_gross_profit')
                                ->label('Lợi nhuận gộp (est.)')
                                ->state(function ($record) {
                                    $revenue = $record->totalPaid();
                                    $expense = (float) $record->expenses()->whereIn('status', ['approved', 'paid'])->sum('total_amount');
                                    return $revenue - $expense;
                                })
                                ->money('VND')
                                ->color(fn ($record) => ($record->totalPaid() - (float) $record->expenses()->whereIn('status', ['approved', 'paid'])->sum('total_amount')) >= 0 ? 'success' : 'danger')
                                ->weight('bold'),
                            TextEntry::make('_expense_count')
                                ->label('Số phiếu chi')
                                ->state(fn ($record) => $record->expenses()->count() . ' phiếu'),
                        ])->columns(5),
                    ]),

                Tab::make('Thông tin hợp đồng')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Section::make()->schema([
                            TextEntry::make('booking.booking_no')
                                ->label('Booking nguồn')
                                ->placeholder('Không từ Booking')
                                ->badge()
                                ->color('primary')
                                ->url(fn ($record) => $record->booking_id
                                    ? \App\Filament\Resources\BookingResource::getUrl('view', ['record' => $record->booking_id])
                                    : null),
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
                            TextEntry::make('customerContact.name')
                                ->label('Người phụ trách (phía KH)')
                                ->placeholder('Chưa chỉ định')
                                ->state(fn ($record) => $record->customerContact
                                    ? $record->customerContact->name . ($record->customerContact->title ? " — {$record->customerContact->title}" : '')
                                    : null),
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
                    ]),

                Tab::make('Phân công')
                    ->icon('heroicon-o-users')
                    ->schema([
                        Section::make()->schema([
                            TextEntry::make('saleOwner.name')->label('Sale phụ trách')->placeholder('Chưa phân công'),
                            TextEntry::make('accountOwner.name')->label('Account phụ trách')->placeholder('Chưa phân công'),
                            TextEntry::make('financeOwner.name')->label('Finance phụ trách')->placeholder('Chưa phân công'),
                        ])->columns(3),
                    ]),

                Tab::make('Ghi chú')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->schema([
                        Section::make()->schema([
                            TextEntry::make('note')
                                ->label('')
                                ->placeholder('Không có ghi chú')
                                ->columnSpanFull(),
                        ]),
                    ]),

            ])->columnSpanFull(),
        ]);
    }
}
