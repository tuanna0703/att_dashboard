<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReceiptResource\Pages;
use App\Filament\Resources\ReceiptResource\RelationManagers;
use App\Models\Receipt;
use App\Models\User;
use App\Support\DepartmentScope;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReceiptResource extends Resource
{
    protected static ?string $model = Receipt::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Công nợ';
    protected static ?int $navigationSort = 3;
    protected static ?string $modelLabel = 'Phiếu thu';
    protected static ?string $pluralModelLabel = 'Phiếu thu';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Thông tin phiếu thu')->schema([
                Forms\Components\DatePicker::make('receipt_date')
                    ->label('Ngày thu')
                    ->required()
                    ->default(now()),
                Forms\Components\TextInput::make('amount')
                    ->label('Số tiền thu')
                    ->numeric()
                    ->prefix('VND')
                    ->required(),
                Forms\Components\Select::make('payment_method')
                    ->label('Hình thức thanh toán')
                    ->options([
                        'bank_transfer' => 'Chuyển khoản',
                        'cash'          => 'Tiền mặt',
                        'cheque'        => 'Séc',
                    ])
                    ->default('bank_transfer')
                    ->required(),
                Forms\Components\TextInput::make('reference_no')
                    ->label('Số tham chiếu / Mã GD'),
                Forms\Components\TextInput::make('bank_account')
                    ->label('Tài khoản ngân hàng'),
                Forms\Components\Select::make('recorded_by')
                    ->label('Người ghi nhận')
                    ->options(User::pluck('name', 'id'))
                    ->default(auth()->id()),
                Forms\Components\Textarea::make('note')
                    ->label('Ghi chú')
                    ->columnSpan(2),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('receipt_date')
                    ->label('Ngày thu')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Số tiền')
                    ->money('VND')
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\BadgeColumn::make('payment_method')
                    ->label('Hình thức')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'bank_transfer' => 'Chuyển khoản',
                        'cash'          => 'Tiền mặt',
                        'cheque'        => 'Séc',
                        default         => $state,
                    })
                    ->colors([
                        'primary' => 'bank_transfer',
                        'success' => 'cash',
                        'warning' => 'cheque',
                    ]),
                Tables\Columns\TextColumn::make('reference_no')
                    ->label('Số TK/Mã GD')
                    ->searchable(),
                Tables\Columns\TextColumn::make('recordedBy.name')
                    ->label('Người ghi')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('allocations_count')
                    ->label('Đã phân bổ')
                    ->counts('allocations')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tạo lúc')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('Hình thức')
                    ->options([
                        'bank_transfer' => 'Chuyển khoản',
                        'cash'          => 'Tiền mặt',
                        'cheque'        => 'Séc',
                    ]),
                Tables\Filters\Filter::make('receipt_date')
                    ->label('Tháng này')
                    ->query(fn ($query) => $query->whereBetween('receipt_date', [now()->startOfMonth(), now()->endOfMonth()])),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->defaultSort('receipt_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\AllocationsRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return DepartmentScope::receipts(parent::getEloquentQuery(), auth()->user());
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasPermissionTo('receipts.viewAny');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->hasPermissionTo('receipts.create');
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasPermissionTo('receipts.update');
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasPermissionTo('receipts.delete');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListReceipts::route('/'),
            'create' => Pages\CreateReceipt::route('/create'),
            'view'   => Pages\ViewReceipt::route('/{record}'),
            'edit'   => Pages\EditReceipt::route('/{record}/edit'),
        ];
    }
}
