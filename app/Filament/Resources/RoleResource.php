<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;
    protected static ?string $navigationIcon  = 'heroicon-o-shield-check';
    protected static ?string $navigationGroup = 'Quản trị hệ thống';
    protected static ?int $navigationSort     = 3;
    protected static ?string $modelLabel      = 'Vai trò';
    protected static ?string $pluralModelLabel = 'Vai trò & Quyền';

    public static function form(Form $form): Form
    {
        // Nhóm permissions theo module để dễ quản lý
        $permissionsByModule = Permission::all()
            ->groupBy(fn ($p) => explode('.', $p->name)[0]);

        $moduleLabels = [
            'customers'        => 'Khách hàng',
            'contracts'        => 'Hợp đồng',
            'invoices'         => 'Hoá đơn',
            'payment_schedules' => 'Đợt thanh toán',
            'receipts'          => 'Phiếu thu',
            'expenses'          => 'Phiếu chi',
            'vendors'           => 'Nhà cung cấp',
            'expense_categories' => 'Danh mục chi phí',
            'departments'       => 'Phòng ban',
            'users'            => 'Người dùng',
            'reports'          => 'Báo cáo',
            'roles'            => 'Hệ thống',
        ];

        $permissionSections = $permissionsByModule->map(function ($permissions, $module) use ($moduleLabels) {
            return Forms\Components\Section::make($moduleLabels[$module] ?? ucfirst($module))
                ->schema([
                    Forms\Components\CheckboxList::make('permissions')
                        ->label('')
                        ->options($permissions->pluck('name', 'name'))
                        ->columns(3)
                        ->gridDirection('row'),
                ])
                ->collapsible()
                ->collapsed(false);
        })->values()->toArray();

        return $form->schema([
            Forms\Components\Section::make('Thông tin vai trò')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Tên vai trò')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(50)
                    ->helperText('Dùng chữ thường, không dấu cách (vd: finance_manager)'),
            ]),

            Forms\Components\Section::make('Phân quyền')->schema([
                Forms\Components\Tabs::make('Modules')
                    ->tabs(
                        $permissionsByModule->map(function ($permissions, $module) use ($moduleLabels) {
                            return Forms\Components\Tabs\Tab::make($moduleLabels[$module] ?? ucfirst($module))
                                ->schema([
                                    Forms\Components\CheckboxList::make('permissions')
                                        ->label('')
                                        ->relationship('permissions', 'name')
                                        ->options($permissions->pluck('name', 'id'))
                                        ->columns(2)
                                        ->gridDirection('row'),
                                ]);
                        })->values()->toArray()
                    )
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Vai trò')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ceo'             => 'danger',
                        'coo'             => 'warning',
                        'vice_ceo'        => 'info',
                        'finance_manager' => 'success',
                        'finance_staff'   => 'gray',
                        default           => 'gray',
                    })
                    ->searchable(),
                Tables\Columns\TextColumn::make('permissions_count')
                    ->label('Số quyền')
                    ->counts('permissions')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('users_count')
                    ->label('Số user')
                    ->counts('users')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ngày tạo')
                    ->date('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
        return auth()->user()->hasPermissionTo('roles.manage');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->hasPermissionTo('roles.manage');
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasPermissionTo('roles.manage');
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false; // Không cho xóa role hệ thống
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit'   => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
