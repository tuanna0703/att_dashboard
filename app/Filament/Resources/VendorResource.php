<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VendorResource\Pages;
use App\Models\Vendor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VendorResource extends Resource
{
    protected static ?string $model = Vendor::class;
    protected static ?string $navigationIcon  = 'heroicon-o-building-storefront';
    protected static ?string $navigationGroup = 'Danh mục';
    protected static ?int    $navigationSort  = 10;
    protected static ?string $modelLabel      = 'Nhà cung cấp';
    protected static ?string $pluralModelLabel = 'Nhà cung cấp';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Thông tin nhà cung cấp')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Tên nhà cung cấp')
                    ->required()
                    ->maxLength(200),
                Forms\Components\TextInput::make('code')
                    ->label('Mã NCC')
                    ->unique(ignoreRecord: true)
                    ->maxLength(50)
                    ->placeholder('VD: NCC-001'),
                Forms\Components\TextInput::make('contact_person')
                    ->label('Người liên hệ')
                    ->maxLength(100),
                Forms\Components\TextInput::make('phone')
                    ->label('Điện thoại')
                    ->tel()
                    ->maxLength(20),
                Forms\Components\TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->maxLength(255),
                Forms\Components\TextInput::make('tax_code')
                    ->label('Mã số thuế')
                    ->maxLength(50),
                Forms\Components\Textarea::make('address')
                    ->label('Địa chỉ')
                    ->rows(2)
                    ->columnSpanFull(),
            ])->columns(2),

            Forms\Components\Section::make('Thông tin ngân hàng')->schema([
                Forms\Components\TextInput::make('bank_name')
                    ->label('Ngân hàng')
                    ->maxLength(200),
                Forms\Components\TextInput::make('bank_account')
                    ->label('Số tài khoản')
                    ->maxLength(50),
            ])->columns(2),

            Forms\Components\Section::make('Khác')->schema([
                Forms\Components\Toggle::make('is_active')
                    ->label('Đang hoạt động')
                    ->default(true),
                Forms\Components\Textarea::make('note')
                    ->label('Ghi chú')
                    ->rows(2)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Mã NCC')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Tên nhà cung cấp')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('contact_person')
                    ->label('Người liên hệ')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Điện thoại')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('tax_code')
                    ->label('MST')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('bank_name')
                    ->label('Ngân hàng')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Hoạt động')
                    ->boolean(),
                Tables\Columns\TextColumn::make('expenses_count')
                    ->label('Số phiếu chi')
                    ->counts('expenses')
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Trạng thái')
                    ->trueLabel('Đang hoạt động')
                    ->falseLabel('Ngừng hoạt động'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasPermissionTo('vendors.viewAny');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->hasPermissionTo('vendors.create');
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasPermissionTo('vendors.update');
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasPermissionTo('vendors.delete');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListVendors::route('/'),
            'create' => Pages\CreateVendor::route('/create'),
            'view'   => Pages\ViewVendor::route('/{record}'),
            'edit'   => Pages\EditVendor::route('/{record}/edit'),
        ];
    }
}
