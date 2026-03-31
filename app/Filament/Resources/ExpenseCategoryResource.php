<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseCategoryResource\Pages;
use App\Models\ExpenseCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ExpenseCategoryResource extends Resource
{
    protected static ?string $model = ExpenseCategory::class;
    protected static ?string $navigationIcon  = 'heroicon-o-tag';
    protected static ?string $navigationGroup = 'Danh mục';
    protected static ?int    $navigationSort  = 11;
    protected static ?string $modelLabel      = 'Danh mục chi phí';
    protected static ?string $pluralModelLabel = 'Danh mục chi phí';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Tên danh mục')
                ->required()
                ->maxLength(200),
            Forms\Components\TextInput::make('code')
                ->label('Mã')
                ->unique(ignoreRecord: true)
                ->maxLength(50),
            Forms\Components\Select::make('type')
                ->label('Loại')
                ->options(ExpenseCategory::$types)
                ->required()
                ->default('general'),
            Forms\Components\Select::make('parent_id')
                ->label('Danh mục cha')
                ->options(fn () => ExpenseCategory::whereNull('parent_id')->pluck('name', 'id'))
                ->searchable()
                ->placeholder('(Không có — là danh mục gốc)'),
            Forms\Components\Toggle::make('is_active')
                ->label('Đang hoạt động')
                ->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Mã')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Tên danh mục')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Danh mục cha')
                    ->placeholder('(Gốc)')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('type')
                    ->label('Loại')
                    ->formatStateUsing(fn ($state) => ExpenseCategory::$types[$state] ?? $state)
                    ->colors([
                        'primary' => 'contract',
                        'success' => 'general',
                    ]),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Hoạt động')
                    ->boolean(),
                Tables\Columns\TextColumn::make('expenses_count')
                    ->label('Số phiếu chi')
                    ->counts('expenses')
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Loại')
                    ->options(ExpenseCategory::$types),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Trạng thái')
                    ->trueLabel('Đang hoạt động')
                    ->falseLabel('Ngừng hoạt động'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('code');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasPermissionTo('expense_categories.viewAny');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->hasPermissionTo('expense_categories.create');
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasPermissionTo('expense_categories.update');
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasPermissionTo('expense_categories.delete');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListExpenseCategories::route('/'),
            'create' => Pages\CreateExpenseCategory::route('/create'),
            'edit'   => Pages\EditExpenseCategory::route('/{record}/edit'),
        ];
    }
}
