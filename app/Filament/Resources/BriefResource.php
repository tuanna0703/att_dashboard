<?php

namespace App\Filament\Resources;

use App\Filament\Forms\LineItemSchema;
use App\Filament\Resources\BriefResource\Pages;
use App\Filament\Resources\BriefResource\RelationManagers;
use App\Filament\Resources\Shared\ActivityLogRelationManager;
use App\Models\Brief;
use App\Models\AdNetwork;
use App\Models\Customer;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Actions\Action as NotifAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
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
                Forms\Components\TextInput::make('campaign_name')
                    ->label('Tên campaign')
                    ->required()
                    ->maxLength(200)
                    ->columnSpanFull(),

                Forms\Components\Grid::make(3)->schema([
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
                ])->columnSpanFull(),
            ]),

            Forms\Components\Section::make('Line Items')->schema([
                Forms\Components\ToggleButtons::make('buying_model')
                    ->label('Loại mua')
                    ->options([
                        'io'  => 'I/O Booking (OOH)',
                        'cpm' => 'CPM (Programmatic)',
                    ])
                    ->icons([
                        'io'  => 'heroicon-o-tv',
                        'cpm' => 'heroicon-o-cursor-arrow-ripple',
                    ])
                    ->default('io')
                    ->required()
                    ->live()
                    ->inline(),

                Forms\Components\Repeater::make('briefLineItems')
                    ->relationship('briefLineItems')
                    ->label('')
                    ->schema(fn (Get $get) => LineItemSchema::schema(
                        buyingModel: $get('buying_model') ?? 'io',
                        withNotes: true,
                    ))
                    ->addActionLabel('+ Thêm line item')
                    ->defaultItems(0)
                    ->reorderable('sort_order')
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(function (array $state): ?string {
                        $networkIds = $state['targeting'] ?? [];
                        if (is_string($networkIds)) {
                            $networkIds = json_decode($networkIds, true) ?? [];
                        }
                        $networks = !empty($networkIds)
                            ? AdNetwork::whereIn('id', (array) $networkIds)->orderBy('name')->pluck('name')->implode(', ')
                            : null;
                        $city  = $state['city'] ?? null;
                        $parts = array_filter([$networks, $state['format'] ?? null, $city]);
                        return $parts ? implode(' — ', $parts) : 'Line item mới';
                    }),

                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\Select::make('currency')
                            ->label('Tiền tệ')
                            ->options(['VND' => 'VND (₫)', 'USD' => 'USD ($)'])
                            ->default('VND')
                            ->required(),
                    ])
                    ->columnSpanFull(),
            ]),

            Forms\Components\Section::make('Chi tiết yêu cầu')->schema([
                Forms\Components\Textarea::make('note')
                    ->label('Ghi chú')
                    ->rows(2)
                    ->columnSpanFull(),

                Forms\Components\FileUpload::make('file_path')
                    ->label('File đính kèm (brief gốc từ khách)')
                    ->disk('public')
                    ->directory('briefs/attachments')
                    ->acceptedFileTypes([
                        'application/pdf',
                        'image/*',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/msword',
                    ])
                    ->getUploadedFileNameForStorageUsing(function (TemporaryUploadedFile $file): string {
                        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                        $ext  = $file->getClientOriginalExtension();
                        $filename = $ext ? "{$name}.{$ext}" : $name;

                        if (Storage::disk('public')->exists("briefs/attachments/{$filename}")) {
                            $filename = $ext ? "{$name}_" . rand(1000, 9999) . ".{$ext}" : "{$name}_" . rand(1000, 9999);
                        }

                        return $filename;
                    })
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('campaign_name')
                    ->label('Brief')
                    ->html()
                    ->formatStateUsing(function (Brief $record) {
                        return '<div class="font-semibold text-gray-950 dark:text-white">' . e($record->campaign_name) . '</div>'
                            . '<div class="text-xs text-gray-400 mt-0.5">' . e($record->brief_no) . '</div>';
                    })
                    ->searchable(query: fn ($query, string $search) =>
                        $query->where('brief_no', 'like', "%{$search}%")
                            ->orWhere('campaign_name', 'like', "%{$search}%")
                    )
                    ->url(fn (Brief $record) => static::getUrl('view', ['record' => $record])),

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

                Tables\Columns\TextColumn::make('budget')
                    ->label('Ngân sách')
                    ->money(fn (Brief $record) => $record->currency ?? 'VND')
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Brief::$statuses[$state] ?? $state)
                    ->color(fn ($state) => Brief::$statusColors[$state] ?? 'gray'),
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

                Tables\Filters\Filter::make('assigned_to_me')
                    ->label('Assign cho tôi')
                    ->query(fn ($query) => $query->where('adops_id', auth()->id()))
                    ->toggle(),

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

                            $adopsUser = User::find($data['adops_id']);
                            if ($adopsUser) {
                                Notification::make()
                                    ->title('Brief mới được assign cho bạn')
                                    ->body("{$record->brief_no} — {$record->campaign_name}")
                                    ->icon('heroicon-o-document-magnifying-glass')
                                    ->iconColor('info')
                                    ->actions([
                                        NotifAction::make('view')
                                            ->label('Xem Brief')
                                            ->url(static::getUrl('view', ['record' => $record])),
                                    ])
                                    ->sendToDatabase($adopsUser);
                            }
                        }),

                    Tables\Actions\DeleteAction::make()
                        ->visible(fn (Brief $record) => $record->status === 'draft'),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelationManagers(): array
    {
        return [
            RelationManagers\PlansRelationManager::class,
            ActivityLogRelationManager::class,
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
