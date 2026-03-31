<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompanyBankResource\Pages;
use App\Models\CompanyBank;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CompanyBankResource extends Resource
{
    protected static ?string $model = CompanyBank::class;
    protected static ?string $navigationIcon  = 'heroicon-o-building-library';
    protected static ?string $navigationGroup = 'Cài đặt';
    protected static ?int $navigationSort     = 5;
    protected static ?string $modelLabel      = 'Tài khoản ngân hàng';
    protected static ?string $pluralModelLabel = 'Tài khoản ngân hàng công ty';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Thông tin ngân hàng')->schema([
                Forms\Components\TextInput::make('bank_name')
                    ->label('Tên ngân hàng')
                    ->required()
                    ->placeholder('VD: Vietcombank, Techcombank, MB Bank...')
                    ->maxLength(255),

                Forms\Components\TextInput::make('account_number')
                    ->label('Số tài khoản')
                    ->required()
                    ->maxLength(50),

                Forms\Components\TextInput::make('account_name')
                    ->label('Tên chủ tài khoản')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('VD: CÔNG TY CP ATT'),

                Forms\Components\TextInput::make('branch')
                    ->label('Chi nhánh')
                    ->placeholder('VD: Chi nhánh Hà Nội')
                    ->maxLength(255),

                Forms\Components\Toggle::make('is_default')
                    ->label('Mặc định')
                    ->helperText('Tài khoản này sẽ được chọn mặc định khi tạo hợp đồng mới.')
                    ->inline(false),

                Forms\Components\Textarea::make('note')
                    ->label('Ghi chú')
                    ->rows(2)
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('bank_name')
                    ->label('Ngân hàng')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('account_number')
                    ->label('Số tài khoản')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Đã sao chép'),

                Tables\Columns\TextColumn::make('account_name')
                    ->label('Tên chủ TK')
                    ->searchable(),

                Tables\Columns\TextColumn::make('branch')
                    ->label('Chi nhánh')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_default')
                    ->label('Mặc định')
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('contracts_count')
                    ->label('Hợp đồng')
                    ->counts('contracts')
                    ->alignCenter(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('is_default', 'desc');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasRole(['ceo', 'coo', 'finance_manager']);
    }

    public static function canCreate(): bool
    {
        return auth()->user()->hasRole(['ceo', 'coo']);
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasRole(['ceo', 'coo']);
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasRole(['ceo', 'coo']);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCompanyBanks::route('/'),
            'create' => Pages\CreateCompanyBank::route('/create'),
            'edit'   => Pages\EditCompanyBank::route('/{record}/edit'),
        ];
    }
}
