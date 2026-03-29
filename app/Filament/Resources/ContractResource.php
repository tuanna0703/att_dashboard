<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContractResource\Pages;
use App\Filament\Resources\ContractResource\RelationManagers;
use App\Models\Contract;
use App\Models\User;
use App\Support\DepartmentScope;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\RawJs;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContractResource extends Resource
{
    protected static ?string $model = Contract::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Hợp đồng';
    protected static ?int $navigationSort = 1;
    protected static ?string $modelLabel = 'Hợp đồng';
    protected static ?string $pluralModelLabel = 'Hợp đồng';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Thông tin hợp đồng')->schema([
                Forms\Components\TextInput::make('contract_code')
                    ->label('Số hợp đồng')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(100),
                Forms\Components\Select::make('contract_type')
                    ->label('Loại hợp đồng')
                    ->options([
                        'ads'          => 'Quảng cáo',
                        'project'      => 'Dự án',
                        'subscription' => 'Thuê bao',
                    ])
                    ->required()
                    ->reactive(),
                Forms\Components\TextInput::make('name')
                    ->label('Tên hợp đồng')
                    ->maxLength(255)
                    ->columnSpan(2)
                    ->placeholder('VD: Hợp đồng quảng cáo Q1/2026'),
                Forms\Components\Select::make('customer_id')
                    ->label('Khách hàng')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->columnSpan(2),
                Forms\Components\DatePicker::make('signed_date')->label('Ngày ký'),
                Forms\Components\DatePicker::make('start_date')->label('Ngày bắt đầu'),
                Forms\Components\DatePicker::make('end_date')->label('Ngày kết thúc'),
                Forms\Components\TextInput::make('total_value_estimated')
                    ->label('Giá trị ước tính')
                    ->required()
                    ->prefix('₫')
                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                    ->dehydrateStateUsing(fn ($state) => (float) str_replace('.', '', $state ?? '0'))
                    ->formatStateUsing(fn ($state) => $state ? number_format((float) $state, 0, ',', '.') : null),
                Forms\Components\Select::make('currency')
                    ->label('Tiền tệ')
                    ->options(['VND' => 'VND', 'USD' => 'USD'])
                    ->default('VND'),
                Forms\Components\Select::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'draft'     => 'Nháp',
                        'active'    => 'Đang chạy',
                        'completed' => 'Hoàn thành',
                        'cancelled' => 'Huỷ',
                    ])
                    ->default('draft')
                    ->required(),
            ])->columns(2),

            Forms\Components\Section::make('Phân công')->schema([
                Forms\Components\Select::make('sale_owner_id')
                    ->label('Sale phụ trách')
                    ->options(User::pluck('name', 'id'))
                    ->searchable(),
                Forms\Components\Select::make('account_owner_id')
                    ->label('Account phụ trách')
                    ->options(User::pluck('name', 'id'))
                    ->searchable(),
                Forms\Components\Select::make('finance_owner_id')
                    ->label('Finance phụ trách')
                    ->options(User::pluck('name', 'id'))
                    ->searchable(),
            ])->columns(3),

            Forms\Components\Section::make('Ghi chú & đính kèm')->schema([
                Forms\Components\Textarea::make('note')
                    ->label('Ghi chú')
                    ->columnSpan(2),
                Forms\Components\FileUpload::make('file_path')
                    ->label('File hợp đồng')
                    ->directory('contracts')
                    ->acceptedFileTypes(['application/pdf', 'image/*']),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('contract_code')
                    ->label('Số HĐ')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Tên hợp đồng')
                    ->searchable()
                    ->limit(40)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Khách hàng')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('contract_type')
                    ->label('Loại')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'ads'          => 'Quảng cáo',
                        'project'      => 'Dự án',
                        'subscription' => 'Thuê bao',
                        default        => $state,
                    })
                    ->colors([
                        'warning' => 'ads',
                        'primary' => 'project',
                        'success' => 'subscription',
                    ]),
                Tables\Columns\TextColumn::make('total_value_estimated')
                    ->label('Giá trị (est.)')
                    ->money('VND')
                    ->sortable(),
                Tables\Columns\TextColumn::make('signed_date')
                    ->label('Ngày ký')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->label('Kết thúc')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn ($record) => $record?->end_date?->isPast() ? 'danger' : null),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Trạng thái')
                    ->colors([
                        'gray'    => 'draft',
                        'success' => 'active',
                        'primary' => 'completed',
                        'danger'  => 'cancelled',
                    ])
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'draft'     => 'Nháp',
                        'active'    => 'Đang chạy',
                        'completed' => 'Hoàn thành',
                        'cancelled' => 'Huỷ',
                        default     => $state,
                    }),
                Tables\Columns\TextColumn::make('saleOwner.name')
                    ->label('Sale')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('financeOwner.name')
                    ->label('Finance')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('contract_type')
                    ->label('Loại hợp đồng')
                    ->options([
                        'ads'          => 'Quảng cáo',
                        'project'      => 'Dự án',
                        'subscription' => 'Thuê bao',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'draft'     => 'Nháp',
                        'active'    => 'Đang chạy',
                        'completed' => 'Hoàn thành',
                        'cancelled' => 'Huỷ',
                    ]),
                Tables\Filters\SelectFilter::make('customer')
                    ->label('Khách hàng')
                    ->relationship('customer', 'name')
                    ->searchable(),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('payment_schedules')
                        ->label('Lịch thanh toán')
                        ->icon('heroicon-o-calendar-days')
                        ->url(fn (Contract $record): string => '/admin/payment-schedules?tableFilters[contract][value]=' . $record->id),
                    Tables\Actions\Action::make('invoices')
                        ->label('Hóa đơn')
                        ->icon('heroicon-o-document-text')
                        ->url(fn (Contract $record): string => '/admin/invoices?tableFilters[contract][value]=' . $record->id),
                    Tables\Actions\Action::make('receipts')
                        ->label('Phiếu thu')
                        ->icon('heroicon-o-banknotes')
                        ->url(fn (Contract $record): string => '/admin/receipts?tableFilters[contract][value]=' . $record->id),
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return DepartmentScope::contracts(parent::getEloquentQuery(), auth()->user());
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasPermissionTo('contracts.viewAny');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->hasPermissionTo('contracts.create');
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasPermissionTo('contracts.update');
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasPermissionTo('contracts.delete');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ContractLinesRelationManager::class,
            RelationManagers\PaymentSchedulesRelationManager::class,
            RelationManagers\InvoicesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListContracts::route('/'),
            'create' => Pages\CreateContract::route('/create'),
            'view'   => Pages\ViewContract::route('/{record}'),
            'edit'   => Pages\EditContract::route('/{record}/edit'),
        ];
    }
}
