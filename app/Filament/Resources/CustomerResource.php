<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers;
use App\Models\Customer;
use App\Support\DepartmentScope;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationGroup = 'Danh mục';
    protected static ?int $navigationSort = 1;
    protected static ?string $modelLabel = 'Khách hàng';
    protected static ?string $pluralModelLabel = 'Khách hàng';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Thông tin cơ bản')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Tên khách hàng')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(2),
                Forms\Components\TextInput::make('tax_code')
                    ->label('Mã số thuế')
                    ->unique(ignoreRecord: true)
                    ->maxLength(20),
                Forms\Components\Select::make('status')
                    ->label('Trạng thái')
                    ->options(['active' => 'Hoạt động', 'inactive' => 'Ngừng hoạt động'])
                    ->default('active')
                    ->required(),
                Forms\Components\Select::make('credit_rating')
                    ->label('Xếp hạng tín dụng')
                    ->options(['A' => 'A - Tốt', 'B' => 'B - Trung bình', 'C' => 'C - Rủi ro', 'D' => 'D - Xấu'])
                    ->default('B')
                    ->required(),
                Forms\Components\Textarea::make('address')
                    ->label('Địa chỉ')
                    ->columnSpan(2),
            ])->columns(2),

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Tên khách hàng')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('tax_code')
                    ->label('MST')
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('credit_rating')
                    ->label('Xếp hạng')
                    ->colors([
                        'success' => 'A',
                        'warning' => 'B',
                        'danger'  => fn ($state) => in_array($state, ['C', 'D']),
                    ]),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Trạng thái')
                    ->colors(['success' => 'active', 'danger' => 'inactive'])
                    ->formatStateUsing(fn ($state) => $state === 'active' ? 'Hoạt động' : 'Ngừng'),
                Tables\Columns\TextColumn::make('contracts_count')
                    ->label('Hợp đồng')
                    ->counts('contracts')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ngày tạo')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options(['active' => 'Hoạt động', 'inactive' => 'Ngừng hoạt động']),
                Tables\Filters\SelectFilter::make('credit_rating')
                    ->label('Xếp hạng tín dụng')
                    ->options(['A' => 'A', 'B' => 'B', 'C' => 'C', 'D' => 'D']),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getEloquentQuery(): Builder
    {
        return DepartmentScope::customers(parent::getEloquentQuery(), auth()->user());
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasPermissionTo('customers.viewAny');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->hasPermissionTo('customers.create');
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasPermissionTo('customers.update');
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasPermissionTo('customers.delete');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ContactsRelationManager::class,
            RelationManagers\ContractsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'view'   => Pages\ViewCustomer::route('/{record}'),
            'edit'   => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
