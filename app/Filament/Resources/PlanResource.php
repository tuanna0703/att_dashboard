<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlanResource\Pages;
use App\Filament\Resources\PlanResource\RelationManagers;
use App\Models\Plan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Support\RawJs;
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

                Forms\Components\TextInput::make('campaign_name')
                    ->label('Tên campaign')
                    ->required()
                    ->maxLength(200)
                    ->columnSpan(2),

                Forms\Components\DatePicker::make('start_date')
                    ->label('Ngày bắt đầu')
                    ->displayFormat('d/m/Y'),

                Forms\Components\DatePicker::make('end_date')
                    ->label('Ngày kết thúc')
                    ->displayFormat('d/m/Y')
                    ->after('start_date'),

                Forms\Components\TextInput::make('budget')
                    ->label('Ngân sách (VND)')
                    ->prefix('₫')
                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                    ->dehydrateStateUsing(fn ($state) => $state ? (float) str_replace('.', '', (string) $state) : null)
                    ->afterStateHydrated(function ($component, $state) {
                        if ($state !== null && $state !== '') {
                            $component->state(number_format((float) $state, 0, ',', '.'));
                        }
                    })
                    ->helperText('Tự động cập nhật khi thêm/xóa line items'),

                Forms\Components\TextInput::make('screen_count')
                    ->label('Số màn hình')
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

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Thông tin kế hoạch')->schema([
                TextEntry::make('plan_no')
                    ->label('Mã Plan')
                    ->weight('bold')
                    ->copyable(),

                TextEntry::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Plan::$statuses[$state] ?? $state)
                    ->color(fn ($state) => Plan::$statusColors[$state] ?? 'gray'),

                TextEntry::make('campaign_name')
                    ->label('Tên campaign')
                    ->columnSpan(2),

                TextEntry::make('brief.brief_no')
                    ->label('Brief')
                    ->url(fn ($record) => BriefResource::getUrl('view', ['record' => $record->brief_id]))
                    ->color('primary'),

                TextEntry::make('createdBy.name')
                    ->label('AdOps')
                    ->placeholder('—'),

                TextEntry::make('start_date')
                    ->label('Ngày bắt đầu')
                    ->date('d/m/Y')
                    ->placeholder('—'),

                TextEntry::make('end_date')
                    ->label('Ngày kết thúc')
                    ->date('d/m/Y')
                    ->placeholder('—'),

                TextEntry::make('budget')
                    ->label('Ngân sách')
                    ->money('VND')
                    ->placeholder('—')
                    ->weight('bold'),

                TextEntry::make('screen_count')
                    ->label('Số màn hình')
                    ->placeholder('—'),

                TextEntry::make('note')
                    ->label('Ghi chú')
                    ->placeholder('—')
                    ->columnSpanFull(),
            ])->columns(4),

            Section::make('Phản hồi của Sale')->schema([
                TextEntry::make('sale_comment')
                    ->label('Comment')
                    ->placeholder('Chưa có phản hồi')
                    ->columnSpanFull(),

                TextEntry::make('respondedBy.name')
                    ->label('Người phản hồi')
                    ->placeholder('—'),

                TextEntry::make('responded_at')
                    ->label('Thời gian')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),
            ])->columns(2)
              ->collapsible()
              ->collapsed(fn ($record) => ! $record->sale_comment),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('plan_no')
                    ->label('Mã Plan')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('version')
                    ->label('Ver.')
                    ->prefix('v')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('brief.brief_no')
                    ->label('Brief')
                    ->searchable()
                    ->url(fn ($record) => BriefResource::getUrl('view', ['record' => $record->brief_id]))
                    ->color('primary'),

                Tables\Columns\TextColumn::make('campaign_name')
                    ->label('Campaign')
                    ->searchable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('AdOps')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Bắt đầu')
                    ->date('d/m/Y')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('budget')
                    ->label('Ngân sách')
                    ->money('VND')
                    ->placeholder('—')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('screen_count')
                    ->label('Màn hình')
                    ->alignCenter()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('lineItems_count')
                    ->label('Line items')
                    ->counts('lineItems')
                    ->alignCenter(),

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
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (Plan $record) => in_array($record->status, ['draft', 're_plan'])
                        && auth()->user()->hasAnyRole(['adops', 'ceo', 'coo'])
                    ),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelationManagers(): array
    {
        return [
            RelationManagers\LineItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPlans::route('/'),
            'view'   => Pages\ViewPlan::route('/{record}'),
            'edit'   => Pages\EditPlan::route('/{record}/edit'),
        ];
    }
}
