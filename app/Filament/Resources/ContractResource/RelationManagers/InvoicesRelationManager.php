<?php

namespace App\Filament\Resources\ContractResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';
    protected static ?string $title = 'Hóa đơn';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('invoice_no')->label('Số HĐ')->required(),
            Forms\Components\DatePicker::make('invoice_date')->label('Ngày xuất')->required(),
            Forms\Components\TextInput::make('invoice_value')->label('Giá trị')->numeric()->prefix('VND')->required(),
            Forms\Components\TextInput::make('vat_value')->label('Tiền VAT')->numeric()->prefix('VND')->default(0),
            Forms\Components\Select::make('status')
                ->label('Trạng thái')
                ->options([
                    'draft'          => 'Nháp',
                    'sent'           => 'Đã gửi',
                    'partially_paid' => 'Thu một phần',
                    'paid'           => 'Đã thu',
                    'cancelled'      => 'Huỷ',
                ])
                ->default('draft'),
            Forms\Components\FileUpload::make('file_path')->label('File HĐ')->directory('invoices'),
            Forms\Components\Textarea::make('note')->label('Ghi chú')->columnSpan(2),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_no')->label('Số HĐ')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('invoice_date')->label('Ngày xuất')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('invoice_value')->label('Giá trị')->money('VND'),
                Tables\Columns\TextColumn::make('vat_value')->label('VAT')->money('VND'),
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
            ->defaultSort('invoice_date', 'desc')
            ->headerActions([Tables\Actions\CreateAction::make()])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ]);
    }
}
