<?php

namespace App\Filament\Resources\ContractResource\RelationManagers;

use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AcceptancesRelationManager extends RelationManager
{
    protected static string $relationship = 'acceptances';
    protected static ?string $title = 'Nghiệm thu';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('acceptance_no')
                ->label('Số biên bản nghiệm thu')
                ->required()
                ->maxLength(100),

            Forms\Components\DatePicker::make('acceptance_date')
                ->label('Ngày nghiệm thu')
                ->required()
                ->default(now())
                ->displayFormat('d/m/Y'),

            Forms\Components\Select::make('adops_id')
                ->label('AdOps thực hiện')
                ->options(User::orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->placeholder('Chọn AdOps'),

            Forms\Components\TextInput::make('accepted_value')
                ->label('Giá trị nghiệm thu (VND)')
                ->numeric()
                ->prefix('₫')
                ->required(),

            Forms\Components\Select::make('status')
                ->label('Trạng thái')
                ->options([
                    'draft'    => 'Nháp',
                    'pending'  => 'Chờ duyệt',
                    'approved' => 'Đã duyệt',
                    'rejected' => 'Từ chối',
                ])
                ->default('draft')
                ->required(),

            Forms\Components\FileUpload::make('file_path')
                ->label('Biên bản đính kèm')
                ->directory('acceptances')
                ->acceptedFileTypes(['application/pdf', 'image/*'])
                ->columnSpanFull(),

            Forms\Components\Textarea::make('note')
                ->label('Ghi chú')
                ->rows(2)
                ->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('acceptance_no')
            ->columns([
                Tables\Columns\TextColumn::make('acceptance_no')
                    ->label('Số biên bản')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('acceptance_date')
                    ->label('Ngày NT')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('adops.name')
                    ->label('AdOps')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('accepted_value')
                    ->label('Giá trị NT')
                    ->money('VND'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Trạng thái')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'draft'    => 'Nháp',
                        'pending'  => 'Chờ duyệt',
                        'approved' => 'Đã duyệt',
                        'rejected' => 'Từ chối',
                        default    => $state,
                    })
                    ->colors([
                        'gray'    => 'draft',
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger'  => 'rejected',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('+ Thêm nghiệm thu'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ]);
    }
}
