<?php

namespace App\Filament\Resources\DepartmentResource\RelationManagers;

use App\Models\DepartmentPosition;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

class PositionsRelationManager extends RelationManager
{
    protected static string $relationship = 'positions';
    protected static ?string $title       = 'Nhân sự';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('user_id')
                ->label('Nhân viên')
                ->options(User::orderBy('name')->pluck('name', 'id'))
                ->required()
                ->searchable(),

            Forms\Components\Select::make('position')
                ->label('Chức vụ')
                ->options(DepartmentPosition::$positions)
                ->required()
                ->default('member'),

            Forms\Components\Select::make('role_id')
                ->label('Vai trò hệ thống')
                ->options(Role::pluck('name', 'id'))
                ->searchable()
                ->placeholder('Giữ nguyên vai trò hiện tại')
                ->helperText('Nếu chọn, vai trò này sẽ được gắn cho user khi lưu.'),

            Forms\Components\Toggle::make('is_primary')
                ->label('Phòng ban chính')
                ->default(false)
                ->helperText('Đánh dấu đây là phòng ban chính của nhân viên.'),

            Forms\Components\DatePicker::make('joined_at')
                ->label('Ngày vào')
                ->displayFormat('d/m/Y')
                ->default(now()),

            Forms\Components\DatePicker::make('left_at')
                ->label('Ngày rời')
                ->displayFormat('d/m/Y')
                ->placeholder('Đang hoạt động'),

            Forms\Components\TextInput::make('note')
                ->label('Ghi chú')
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Nhân viên')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('position')
                    ->label('Chức vụ')
                    ->badge()
                    ->formatStateUsing(fn ($state) => DepartmentPosition::$positions[$state] ?? $state)
                    ->color(fn ($state) => DepartmentPosition::$positionColors[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('role.name')
                    ->label('Vai trò')
                    ->badge()
                    ->color('info')
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('is_primary')
                    ->label('Chính')
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('joined_at')
                    ->label('Ngày vào')
                    ->date('d/m/Y')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('left_at')
                    ->label('Ngày rời')
                    ->date('d/m/Y')
                    ->placeholder('Đang hoạt động')
                    ->color(fn ($state) => $state ? 'danger' : null),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('active')
                    ->label('Trạng thái')
                    ->placeholder('Tất cả')
                    ->trueLabel('Đang hoạt động')
                    ->falseLabel('Đã rời')
                    ->queries(
                        true: fn ($query) => $query->whereNull('left_at'),
                        false: fn ($query) => $query->whereNotNull('left_at'),
                    ),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('+ Thêm nhân sự')
                    ->after(function ($record) {
                        // Gắn role cho user nếu được chọn
                        if ($record->role_id) {
                            $role = Role::find($record->role_id);
                            if ($role) {
                                $record->user->syncRoles([$role->name]);
                            }
                        }
                        // Cập nhật department_id nếu là phòng ban chính
                        if ($record->is_primary) {
                            $record->user->update(['department_id' => $record->department_id]);
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->after(function ($record) {
                        if ($record->role_id) {
                            $role = Role::find($record->role_id);
                            if ($role) {
                                $record->user->syncRoles([$role->name]);
                            }
                        }
                        if ($record->is_primary) {
                            $record->user->update(['department_id' => $record->department_id]);
                        }
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('position');
    }
}
