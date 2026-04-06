<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\Department;
use App\Models\DepartmentPosition;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon  = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Quản trị hệ thống';
    protected static ?int $navigationSort     = 2;
    protected static ?string $modelLabel      = 'Người dùng';
    protected static ?string $pluralModelLabel = 'Người dùng';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Thông tin tài khoản')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Họ và tên')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Forms\Components\TextInput::make('password')
                    ->label('Mật khẩu')
                    ->password()
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $context) => $context === 'create')
                    ->hint(fn (string $context) => $context === 'edit' ? 'Để trống nếu không đổi mật khẩu' : null)
                    ->maxLength(255),
            ])->columns(2),

            Forms\Components\Section::make('Phân quyền & Phòng ban')->schema([
                Forms\Components\Select::make('roles')
                    ->label('Vai trò')
                    ->options(Role::pluck('name', 'name'))
                    ->relationship('roles', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('department_id')
                    ->label('Phòng ban')
                    ->options(Department::where('is_active', true)->pluck('name', 'id'))
                    ->searchable()
                    ->nullable()
                    ->placeholder('Không thuộc phòng ban nào'),
                Forms\Components\Select::make('overseenDepartments')
                    ->label('Phòng ban giám sát (Vice CEO)')
                    ->options(Department::where('is_active', true)->pluck('name', 'id'))
                    ->relationship('overseenDepartments', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->hint('Chỉ dùng cho Vice CEO')
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Họ và tên')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Vai trò')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ceo'             => 'danger',
                        'coo'             => 'warning',
                        'vice_ceo'        => 'info',
                        'finance_manager' => 'success',
                        'finance_staff'   => 'gray',
                        default           => 'gray',
                    }),
                Tables\Columns\TextColumn::make('department.name')
                    ->label('Phòng ban')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('primary_position')
                    ->label('Chức vụ')
                    ->getStateUsing(function (User $record) {
                        $pos = $record->departmentPositions()
                            ->where('is_primary', true)
                            ->whereNull('left_at')
                            ->first();
                        return $pos ? (DepartmentPosition::$positions[$pos->position] ?? $pos->position) : null;
                    })
                    ->badge()
                    ->color(function ($state) {
                        $key = array_search($state, DepartmentPosition::$positions);
                        return DepartmentPosition::$positionColors[$key] ?? 'gray';
                    })
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ngày tạo')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('department_id')
                    ->label('Phòng ban')
                    ->options(Department::pluck('name', 'id')),
                Tables\Filters\SelectFilter::make('roles')
                    ->label('Vai trò')
                    ->relationship('roles', 'name'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasPermissionTo('users.viewAny');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->hasPermissionTo('users.create');
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasPermissionTo('users.update');
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasPermissionTo('users.delete');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
