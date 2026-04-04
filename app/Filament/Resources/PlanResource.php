<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BriefResource;
use App\Filament\Resources\PlanResource\Pages;
use App\Filament\Resources\PlanResource\RelationManagers;
use App\Filament\Resources\Shared\ActivityLogRelationManager;
use App\Models\Plan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;
    protected static ?string $navigationIcon  = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Booking';
    protected static ?int    $navigationSort  = 8;
    protected static ?string $modelLabel       = 'Plan';
    protected static ?string $pluralModelLabel = 'Plans';

    // Plans are created from Brief context — hide "New Plan" button globally
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasPermissionTo('plans.viewAny');
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasPermissionTo('plans.update')
            && in_array($record->status, ['draft', 're_plan']);
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasPermissionTo('plans.delete');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Thông tin kế hoạch')->schema([
                Forms\Components\TextInput::make('plan_no')
                    ->label('Mã Plan')
                    ->disabled(),

                Forms\Components\TextInput::make('version')
                    ->label('Phiên bản')
                    ->prefix('v')
                    ->disabled(),

                Forms\Components\TextInput::make('budget')
                    ->label('Ngân sách (VND)')
                    ->prefix('₫')
                    ->disabled()
                    ->helperText('Tự động tính từ line items'),

                Forms\Components\TextInput::make('screen_count')
                    ->label('Số line items')
                    ->numeric()
                    ->disabled()
                    ->helperText('Tự động tính từ line items'),

                Forms\Components\Textarea::make('note')
                    ->label('Ghi chú')
                    ->rows(3)
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('brief.campaign_name')
                    ->label('Plan')
                    ->description(fn (Plan $record) => $record->plan_no . ' · v' . $record->version)
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->url(fn (Plan $record) => static::getUrl('view', ['record' => $record])),

                Tables\Columns\TextColumn::make('brief.customer.name')
                    ->label('Khách hàng')
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('brief.sale.name')
                    ->label('Sale')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('adops.name')
                    ->label('AdOps')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('budget')
                    ->label('Ngân sách')
                    ->money(fn (Plan $record) => $record->brief?->currency ?? 'VND')
                    ->placeholder('—')
                    ->alignEnd(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Trạng thái')
                    ->formatStateUsing(fn ($state) => Plan::$statuses[$state] ?? $state)
                    ->colors(Plan::$statusColors),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options(Plan::$statuses),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make()
                        ->visible(fn (Plan $record) => in_array($record->status, ['draft', 're_plan'])
                            && auth()->user()->hasAnyRole(['adops', 'ceo', 'coo'])
                        ),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelationManagers(): array
    {
        return [
            RelationManagers\LineItemsRelationManager::class,
            ActivityLogRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPlans::route('/'),
            'create' => Pages\CreatePlan::route('/create'),
            'view'   => Pages\ViewPlan::route('/{record}'),
            'edit'   => Pages\EditPlan::route('/{record}/edit'),
        ];
    }
}
