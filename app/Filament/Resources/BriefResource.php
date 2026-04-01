<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BriefResource\Pages;
use App\Filament\Resources\BriefResource\RelationManagers;
use App\Models\AdNetwork;
use App\Models\Brief;
use App\Models\Customer;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\RawJs;
use Filament\Tables;
use Filament\Tables\Table;

class BriefResource extends Resource
{
    protected static ?string $model = Brief::class;
    protected static ?string $navigationIcon  = 'heroicon-o-document-magnifying-glass';
    protected static ?string $navigationGroup = 'Booking';
    protected static ?int    $navigationSort  = 2;
    protected static ?string $modelLabel      = 'Brief';
    protected static ?string $pluralModelLabel = 'Briefs';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Thông tin campaign')->schema([
                Forms\Components\TextInput::make('brief_no')
                    ->label('Mã Brief')
                    ->placeholder('Tự động tạo')
                    ->disabled()
                    ->dehydrated(false),

                Forms\Components\Select::make('status')
                    ->label('Trạng thái')
                    ->options(Brief::$statuses)
                    ->disabled()
                    ->dehydrated(false),

                Forms\Components\TextInput::make('campaign_name')
                    ->label('Tên campaign')
                    ->required()
                    ->maxLength(200)
                    ->columnSpan(2),

                Forms\Components\Select::make('customer_id')
                    ->label('Khách hàng')
                    ->options(Customer::orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->required(),

                Forms\Components\Select::make('sale_id')
                    ->label('Sale phụ trách')
                    ->options(User::orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->required()
                    ->default(auth()->id()),

                Forms\Components\Select::make('adops_id')
                    ->label('AdOps phụ trách')
                    ->options(User::orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->placeholder('Chưa assign'),

                Forms\Components\DatePicker::make('start_date')
                    ->label('Ngày bắt đầu')
                    ->required()
                    ->displayFormat('d/m/Y'),

                Forms\Components\DatePicker::make('end_date')
                    ->label('Ngày kết thúc')
                    ->required()
                    ->displayFormat('d/m/Y')
                    ->after('start_date'),
            ])->columns(2),

            Forms\Components\Section::make('Chi tiết yêu cầu')->schema([
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
                    ->placeholder('Không bắt buộc'),

                Forms\Components\TextInput::make('screen_count')
                    ->label('Số màn hình')
                    ->numeric()
                    ->minValue(1)
                    ->placeholder('Không bắt buộc'),

                Forms\Components\TextInput::make('cpm')
                    ->label('CPM (VND)')
                    ->prefix('₫')
                    ->numeric()
                    ->placeholder('Không bắt buộc'),

                Forms\Components\TextInput::make('duration_days')
                    ->label('Số ngày chạy')
                    ->numeric()
                    ->minValue(1)
                    ->placeholder('Không bắt buộc'),

                Forms\Components\Textarea::make('note')
                    ->label('Ghi chú')
                    ->rows(2)
                    ->columnSpanFull(),

                Forms\Components\FileUpload::make('file_path')
                    ->label('File đính kèm (brief gốc từ khách)')
                    ->directory('briefs/attachments')
                    ->acceptedFileTypes([
                        'application/pdf',
                        'image/*',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/msword',
                    ])
                    ->columnSpanFull(),
            ])->columns(2),

            Forms\Components\Section::make('Mạng lưới quảng cáo')->schema([
                Forms\Components\Repeater::make('briefAdNetworks')
                    ->relationship('briefAdNetworks')
                    ->label('')
                    ->schema([
                        Forms\Components\Select::make('ad_network_id')
                            ->label('Mạng lưới')
                            ->options(AdNetwork::where('is_active', true)->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('screen_count')
                            ->label('Số màn hình')
                            ->numeric()
                            ->minValue(1),

                        Forms\Components\TextInput::make('note')
                            ->label('Ghi chú')
                            ->columnSpan(2),
                    ])
                    ->columns(5)
                    ->addActionLabel('+ Thêm mạng lưới')
                    ->defaultItems(0)
                    ->collapsible()
                    ->itemLabel(function (array $state): ?string {
                        $networkId = $state['ad_network_id'] ?? null;
                        if (! $networkId) return null;
                        $network = AdNetwork::find($networkId);
                        $screens = $state['screen_count'] ?? null;
                        return $network?->name . ($screens ? " — {$screens} màn hình" : '');
                    }),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('brief_no')
                    ->label('Mã Brief')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('campaign_name')
                    ->label('Tên Campaign')
                    ->searchable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Khách hàng')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sale.name')
                    ->label('Sale')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('adops.name')
                    ->label('AdOps')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Bắt đầu')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_date')
                    ->label('Kết thúc')
                    ->date('d/m/Y'),

                Tables\Columns\TextColumn::make('budget')
                    ->label('Ngân sách')
                    ->money('VND')
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('revisions_count')
                    ->label('Rev.')
                    ->counts('revisions')
                    ->alignCenter(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Trạng thái')
                    ->formatStateUsing(fn ($state) => Brief::$statuses[$state] ?? $state)
                    ->colors(Brief::$statusColors),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options(Brief::$statuses),

                Tables\Filters\SelectFilter::make('customer_id')
                    ->label('Khách hàng')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('sale_id')
                    ->label('Sale')
                    ->relationship('sale', 'name')
                    ->searchable(),

                Tables\Filters\SelectFilter::make('adops_id')
                    ->label('AdOps')
                    ->relationship('adops', 'name')
                    ->searchable(),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make()
                        ->visible(fn (Brief $record) => in_array($record->status, ['draft', 'customer_feedback'])),

                    // Gửi brief cho AdOps
                    Tables\Actions\Action::make('send_to_adops')
                        ->label('Gửi AdOps')
                        ->icon('heroicon-o-arrow-right-circle')
                        ->color('info')
                        ->visible(fn (Brief $record) => $record->status === 'draft')
                        ->form([
                            Forms\Components\Select::make('adops_id')
                                ->label('Assign cho AdOps')
                                ->options(User::orderBy('name')->pluck('name', 'id'))
                                ->required()
                                ->searchable(),
                        ])
                        ->modalHeading('Gửi Brief cho AdOps')
                        ->action(function (Brief $record, array $data) {
                            $record->update([
                                'status'   => 'sent_to_adops',
                                'adops_id' => $data['adops_id'],
                            ]);
                            Notification::make()->title('Đã gửi Brief cho AdOps')->success()->send();
                        }),

                    // AdOps báo đã có planning
                    Tables\Actions\Action::make('planning_ready')
                        ->label('Planning sẵn sàng')
                        ->icon('heroicon-o-clipboard-document-check')
                        ->color('warning')
                        ->visible(fn (Brief $record) => $record->status === 'sent_to_adops')
                        ->requiresConfirmation()
                        ->modalHeading('Xác nhận planning đã sẵn sàng?')
                        ->action(function (Brief $record) {
                            $record->update(['status' => 'planning_ready']);
                            Notification::make()->title('Brief đã có planning — có thể gửi khách')->success()->send();
                        }),

                    // Convert sang Booking (khi đã confirmed)
                    Tables\Actions\Action::make('convert_to_booking')
                        ->label('Tạo Booking')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->color('primary')
                        ->visible(fn (Brief $record) => $record->status === 'confirmed')
                        ->requiresConfirmation()
                        ->modalHeading('Chuyển Brief thành Booking?')
                        ->modalDescription('Revision cuối cùng (is_final) sẽ được áp dụng cho Booking.')
                        ->action(function (Brief $record) {
                            $finalRevision = $record->revisions()->where('is_final', true)->first();
                            if (! $finalRevision) {
                                Notification::make()->title('Không tìm thấy revision cuối cùng')->danger()->send();
                                return;
                            }
                            $booking = \App\Models\Booking::create([
                                'brief_id'          => $record->id,
                                'brief_revision_id' => $finalRevision->id,
                                'customer_id'       => $record->customer_id,
                                'sale_id'           => $record->sale_id,
                                'adops_id'          => $record->adops_id,
                                'campaign_name'     => $record->campaign_name,
                                'start_date'        => $record->start_date,
                                'end_date'          => $record->end_date,
                                'total_budget'      => $record->budget,
                                'status'            => 'pending_contract',
                            ]);
                            $record->update(['status' => 'converted']);
                            Notification::make()
                                ->title("Đã tạo Booking {$booking->booking_no}")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\DeleteAction::make()
                        ->visible(fn (Brief $record) => $record->status === 'draft'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelationManagers(): array
    {
        return [
            RelationManagers\RevisionsRelationManager::class,
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasPermissionTo('briefs.viewAny');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->hasPermissionTo('briefs.create');
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasPermissionTo('briefs.update');
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasPermissionTo('briefs.delete');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListBriefs::route('/'),
            'create' => Pages\CreateBrief::route('/create'),
            'view'   => Pages\ViewBrief::route('/{record}'),
            'edit'   => Pages\EditBrief::route('/{record}/edit'),
        ];
    }
}
