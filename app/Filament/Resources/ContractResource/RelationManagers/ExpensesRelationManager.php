<?php

namespace App\Filament\Resources\ContractResource\RelationManagers;

use App\Filament\Resources\ExpenseResource;
use App\Models\Expense;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ExpensesRelationManager extends RelationManager
{
    protected static string $relationship = 'expenses';
    protected static ?string $title = 'Chi phí';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('expense_no')
                    ->label('Mã phiếu')
                    ->weight('bold')
                    ->sortable(),

                Tables\Columns\TextColumn::make('expense_date')
                    ->label('Ngày chi')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Danh mục')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('vendor.name')
                    ->label('Nhà cung cấp')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Tổng tiền')
                    ->money('VND')
                    ->weight('bold')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Trạng thái')
                    ->formatStateUsing(fn ($state) => Expense::$statuses[$state] ?? $state)
                    ->colors(Expense::$statusColors),

                Tables\Columns\TextColumn::make('recordedBy.name')
                    ->label('Người lập')
                    ->placeholder('—'),
            ])
            ->defaultSort('expense_date', 'desc')
            ->headerActions([
                Tables\Actions\Action::make('create_expense')
                    ->label('Thêm phiếu chi')
                    ->icon('heroicon-o-plus')
                    ->url(fn () => ExpenseResource::getUrl('create') . '?contract_id=' . $this->getOwnerRecord()->id),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Xem')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Expense $record) => ExpenseResource::getUrl('view', ['record' => $record])),
                Tables\Actions\Action::make('edit')
                    ->label('Sửa')
                    ->icon('heroicon-o-pencil')
                    ->visible(fn (Expense $record) => in_array($record->status, ['draft', 'rejected']))
                    ->url(fn (Expense $record) => ExpenseResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
