<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Models\CustomerContact;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components\BadgeEntry;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create_contact')
                ->label('Thêm người liên hệ')
                ->icon('heroicon-o-user-plus')
                ->color('primary')
                ->modalWidth('2xl')
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
                    $this->record->contacts()->create($data);
                    Notification::make()
                        ->title('Đã thêm người liên hệ')
                        ->success()
                        ->send();
                }),

            Actions\EditAction::make()->label('Chỉnh sửa'),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([

            Section::make('Thông tin khách hàng')->schema([
                TextEntry::make('name')
                    ->label('Tên khách hàng')
                    ->weight('bold')
                    ->size('lg')
                    ->columnSpan(2),
                TextEntry::make('tax_code')
                    ->label('Mã số thuế')
                    ->copyable()
                    ->placeholder('—'),
                TextEntry::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === 'active' ? 'Hoạt động' : 'Ngừng hoạt động')
                    ->color(fn ($state) => $state === 'active' ? 'success' : 'danger'),
                TextEntry::make('credit_rating')
                    ->label('Xếp hạng tín dụng')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'A' => 'A — Tốt',
                        'B' => 'B — Trung bình',
                        'C' => 'C — Rủi ro',
                        'D' => 'D — Xấu',
                        default => $state,
                    })
                    ->color(fn ($state) => match ($state) {
                        'A' => 'success',
                        'B' => 'warning',
                        'C', 'D' => 'danger',
                        default => 'gray',
                    }),
                TextEntry::make('address')
                    ->label('Địa chỉ')
                    ->placeholder('—')
                    ->columnSpan(2),
            ])->columns(3),

            Section::make('Tổng quan tài chính')->schema([
                TextEntry::make('_contracts_count')
                    ->label('Số hợp đồng')
                    ->state(fn ($record) => $record->contracts()->count() . ' hợp đồng'),
                TextEntry::make('_outstanding')
                    ->label('Công nợ chưa thu (AR)')
                    ->state(fn ($record) => $record->totalOutstanding())
                    ->money('VND')
                    ->color('warning')
                    ->weight('bold'),
                TextEntry::make('_overdue')
                    ->label('Quá hạn')
                    ->state(fn ($record) => $record->totalOverdue())
                    ->money('VND')
                    ->color(fn ($record) => $record->totalOverdue() > 0 ? 'danger' : 'gray')
                    ->weight('bold'),
            ])->columns(3),

        ]);
    }
}
