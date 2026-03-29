<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use App\Models\CustomerContact;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'contacts';
    protected static ?string $title = 'Người liên hệ';
    protected static ?string $modelLabel = 'Người liên hệ';

    public static function canViewForRecord(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()->hasPermissionTo('customer_contacts.viewAny');
    }

    public function canCreate(): bool
    {
        return auth()->user()->hasPermissionTo('customer_contacts.create');
    }

    public function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasPermissionTo('customer_contacts.update');
    }

    public function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasPermissionTo('customer_contacts.delete');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
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
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\IconColumn::make('is_primary')
                    ->label('')
                    ->boolean()
                    ->trueIcon('heroicon-s-star')
                    ->falseIcon('')
                    ->trueColor('warning')
                    ->tooltip('Người liên hệ chính')
                    ->width('40px'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Họ và tên')
                    ->weight('bold')
                    ->searchable(),
                Tables\Columns\TextColumn::make('title')
                    ->label('Chức danh')
                    ->placeholder('—'),
                Tables\Columns\BadgeColumn::make('role')
                    ->label('Vai trò')
                    ->formatStateUsing(fn ($state) => CustomerContact::$roles[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'management' => 'danger',
                        'contract'   => 'primary',
                        'booking'    => 'info',
                        'payment'    => 'success',
                        default      => 'gray',
                    }),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Điện thoại')
                    ->icon('heroicon-m-phone')
                    ->copyable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->icon('heroicon-m-envelope')
                    ->copyable()
                    ->placeholder('—'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Thêm người liên hệ'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->defaultSort('is_primary', 'desc');
    }
}
