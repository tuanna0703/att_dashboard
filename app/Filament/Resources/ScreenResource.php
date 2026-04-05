<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ScreenResource\Pages;
use App\Models\AdNetwork;
use App\Models\Screen;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ScreenResource extends Resource
{
    protected static ?string $model = Screen::class;
    protected static ?string $navigationIcon  = 'heroicon-o-tv';
    protected static ?string $navigationGroup = 'Inventory';
    protected static ?int    $navigationSort  = 1;
    protected static ?string $modelLabel       = 'Màn hình';
    protected static ?string $pluralModelLabel = 'Màn hình';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Thông tin cơ bản')->schema([
                Forms\Components\TextInput::make('code')
                    ->label('Mã màn hình')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(50)
                    ->placeholder('VD: SCR-HN-001'),

                Forms\Components\TextInput::make('name')
                    ->label('Tên màn hình')
                    ->required()
                    ->maxLength(200),

                Forms\Components\Select::make('status')
                    ->label('Trạng thái')
                    ->options(Screen::$statuses)
                    ->default('active')
                    ->required(),

                Forms\Components\Select::make('ad_network_id')
                    ->label('Mạng lưới QC')
                    ->options(AdNetwork::where('is_active', true)->pluck('name', 'id'))
                    ->searchable()
                    ->nullable(),
            ])->columns(2),

            Forms\Components\Section::make('Địa điểm')->schema([
                Forms\Components\TextInput::make('venue_name')
                    ->label('Tên địa điểm')
                    ->maxLength(200)
                    ->placeholder('VD: Vincom Center Bà Triệu'),

                Forms\Components\Select::make('venue_type')
                    ->label('Loại địa điểm')
                    ->options(Screen::$venueTypes)
                    ->searchable(),

                Forms\Components\TextInput::make('location_city')
                    ->label('Thành phố')
                    ->maxLength(100)
                    ->placeholder('VD: Hà Nội'),

                Forms\Components\TextInput::make('province')
                    ->label('Tỉnh/Thành')
                    ->maxLength(100),

                Forms\Components\Textarea::make('location_address')
                    ->label('Địa chỉ chi tiết')
                    ->rows(2)
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('latitude')
                    ->label('Vĩ độ (Latitude)')
                    ->numeric()
                    ->placeholder('21.028511'),

                Forms\Components\TextInput::make('longitude')
                    ->label('Kinh độ (Longitude)')
                    ->numeric()
                    ->placeholder('105.834160'),
            ])->columns(2),

            Forms\Components\Section::make('Thông số kỹ thuật')->schema([
                Forms\Components\TextInput::make('width_px')
                    ->label('Chiều rộng (px)')
                    ->numeric()
                    ->suffix('px'),

                Forms\Components\TextInput::make('height_px')
                    ->label('Chiều cao (px)')
                    ->numeric()
                    ->suffix('px'),

                Forms\Components\TextInput::make('resolution')
                    ->label('Độ phân giải')
                    ->maxLength(50)
                    ->placeholder('VD: 1920x1080'),

                Forms\Components\TextInput::make('slot_duration_seconds')
                    ->label('Thời lượng 1 slot')
                    ->numeric()
                    ->default(15)
                    ->suffix('giây'),

                Forms\Components\TextInput::make('total_slots_per_hour')
                    ->label('Slots / giờ')
                    ->numeric()
                    ->default(4),

                Forms\Components\TextInput::make('operational_hours')
                    ->label('Giờ hoạt động / ngày')
                    ->numeric()
                    ->default(18)
                    ->suffix('giờ'),
            ])->columns(3),

            Forms\Components\Section::make('Bảng giá')->schema([
                Forms\Components\TextInput::make('rate_card_cpm')
                    ->label('Rate card CPM')
                    ->numeric()
                    ->prefix('₫')
                    ->placeholder('0'),

                Forms\Components\TextInput::make('rate_card_daily')
                    ->label('Rate card Daily')
                    ->numeric()
                    ->prefix('₫')
                    ->placeholder('0'),
            ])->columns(2),

            Forms\Components\Section::make('Ghi chú')->schema([
                Forms\Components\Textarea::make('notes')
                    ->label('Ghi chú')
                    ->rows(3)
                    ->columnSpanFull(),
            ]),
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
                    ->label('Tên màn hình')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Screen $r) => $r->venue_name),

                Tables\Columns\TextColumn::make('venue_type')
                    ->label('Loại')
                    ->formatStateUsing(fn ($state) => Screen::$venueTypes[$state] ?? $state)
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('location_city')
                    ->label('Thành phố')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('adNetwork.name')
                    ->label('Mạng lưới')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('rate_card_daily')
                    ->label('Rate Daily')
                    ->money('VND')
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('daily_slots')
                    ->label('Slots/ngày')
                    ->getStateUsing(fn (Screen $r) => $r->daily_slots)
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->formatStateUsing(fn ($state) => Screen::$statuses[$state] ?? $state)
                    ->badge()
                    ->color(fn ($state) => Screen::$statusColors[$state] ?? 'gray')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options(Screen::$statuses),

                Tables\Filters\SelectFilter::make('venue_type')
                    ->label('Loại địa điểm')
                    ->options(Screen::$venueTypes),

                Tables\Filters\SelectFilter::make('location_city')
                    ->label('Thành phố')
                    ->options(
                        Screen::select('location_city')
                            ->whereNotNull('location_city')
                            ->distinct()
                            ->orderBy('location_city')
                            ->pluck('location_city', 'location_city')
                    ),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                    Tables\Actions\RestoreAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('code');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasPermissionTo('screens.viewAny');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->hasPermissionTo('screens.create');
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasPermissionTo('screens.update');
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasPermissionTo('screens.delete');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListScreens::route('/'),
            'create' => Pages\CreateScreen::route('/create'),
            'edit'   => Pages\EditScreen::route('/{record}/edit'),
        ];
    }
}
