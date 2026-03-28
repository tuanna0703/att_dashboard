<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceResource\Pages;
use App\Models\Invoice;
use App\Support\DepartmentScope;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;
    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';
    protected static ?string $navigationGroup = 'Công nợ';
    protected static ?int $navigationSort = 2;
    protected static ?string $modelLabel = 'Hóa đơn';
    protected static ?string $pluralModelLabel = 'Hóa đơn';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\Select::make('contract_id')
                    ->label('Hợp đồng')
                    ->relationship('contract', 'contract_code')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->columnSpan(2),
                Forms\Components\TextInput::make('invoice_no')
                    ->label('Số hóa đơn')
                    ->required()
                    ->unique(ignoreRecord: true),
                Forms\Components\DatePicker::make('invoice_date')
                    ->label('Ngày xuất hóa đơn')
                    ->required(),
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
                        'draft'          => 'Nháp',
                        'sent'           => 'Đã gửi KH',
                        'partially_paid' => 'Thu một phần',
                        'paid'           => 'Đã thu',
                        'cancelled'      => 'Huỷ',
                    ])
                    ->default('draft')
                    ->required(),
                Forms\Components\FileUpload::make('file_path')
                    ->label('File hóa đơn')
                    ->directory('invoices')
                    ->acceptedFileTypes(['application/pdf', 'image/*']),
                Forms\Components\Textarea::make('note')->label('Ghi chú')->columnSpan(2),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_no')
                    ->label('Số HĐ')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('contract.customer.name')
                    ->label('Khách hàng')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('contract.contract_code')
                    ->label('Hợp đồng')
                    ->searchable(),
                Tables\Columns\TextColumn::make('invoice_date')
                    ->label('Ngày xuất')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('invoice_value')
                    ->label('Giá trị')
                    ->money('VND')
                    ->sortable(),
                Tables\Columns\TextColumn::make('vat_value')
                    ->label('VAT')
                    ->money('VND')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Trạng thái')
                    ->colors([
                        'gray'    => 'draft',
                        'primary' => 'sent',
                        'warning' => 'partially_paid',
                        'success' => 'paid',
                        'danger'  => 'cancelled',
                    ])
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'draft'          => 'Nháp',
                        'sent'           => 'Đã gửi',
                        'partially_paid' => 'Thu 1 phần',
                        'paid'           => 'Đã thu',
                        'cancelled'      => 'Huỷ',
                        default          => $state,
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'draft'          => 'Nháp',
                        'sent'           => 'Đã gửi',
                        'partially_paid' => 'Thu một phần',
                        'paid'           => 'Đã thu',
                        'cancelled'      => 'Huỷ',
                    ]),
                Tables\Filters\Filter::make('unpaid')
                    ->label('Chưa thu')
                    ->query(fn ($query) => $query->whereNotIn('status', ['paid', 'cancelled'])),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('invoice_date', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return DepartmentScope::invoices(parent::getEloquentQuery(), auth()->user());
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasPermissionTo('invoices.viewAny');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->hasPermissionTo('invoices.create');
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasPermissionTo('invoices.update');
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasPermissionTo('invoices.delete');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'edit'   => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }
}
