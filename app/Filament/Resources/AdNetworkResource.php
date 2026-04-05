<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdNetworkResource\Pages;
use App\Models\AdNetwork;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AdNetworkResource extends Resource
{
    protected static ?string $model = AdNetwork::class;
    protected static ?string $navigationIcon  = 'heroicon-o-signal';
    protected static ?string $navigationGroup = 'Inventory';
    protected static ?int    $navigationSort  = 2;
    protected static ?string $modelLabel      = 'Mạng lưới QC';
    protected static ?string $pluralModelLabel = 'Mạng lưới QC';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Thông tin mạng lưới')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Tên mạng lưới')
                    ->required()
                    ->maxLength(100)
                    ->placeholder('VD: Winmart+, Aeon Mall'),

                Forms\Components\TextInput::make('code')
                    ->label('Mã')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(50)
                    ->placeholder('VD: WINMART, AEON'),

                Forms\Components\Textarea::make('description')
                    ->label('Mô tả')
                    ->rows(2)
                    ->columnSpanFull(),

                Forms\Components\Toggle::make('is_active')
                    ->label('Đang hoạt động')
                    ->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Mã')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Tên mạng lưới')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Mô tả')
                    ->placeholder('—')
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Hoạt động')
                    ->boolean(),

                Tables\Columns\TextColumn::make('mediaBuyingOrderItems_count')
                    ->label('Số MBO items')
                    ->counts('mediaBuyingOrderItems')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Trạng thái')
                    ->trueLabel('Đang hoạt động')
                    ->falseLabel('Ngừng hoạt động'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasPermissionTo('ad_networks.viewAny');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->hasPermissionTo('ad_networks.create');
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasPermissionTo('ad_networks.update');
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasPermissionTo('ad_networks.delete');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAdNetworks::route('/'),
            'create' => Pages\CreateAdNetwork::route('/create'),
            'edit'   => Pages\EditAdNetwork::route('/{record}/edit'),
        ];
    }
}
