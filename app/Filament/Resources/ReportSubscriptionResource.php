<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReportSubscriptionResource\Pages;
use App\Models\ReportSubscription;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ReportSubscriptionResource extends Resource
{
    protected static ?string $model = ReportSubscription::class;
    protected static ?string $navigationIcon = 'heroicon-o-envelope';
    protected static ?string $navigationGroup = 'Cài đặt';
    protected static ?int $navigationSort = 10;
    protected static ?string $modelLabel = 'Báo cáo email';
    protected static ?string $pluralModelLabel = 'Báo cáo email';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Thông tin báo cáo')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Tên báo cáo')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('VD: Báo cáo quá hạn hàng ngày'),

                Forms\Components\Select::make('report_type')
                    ->label('Loại báo cáo')
                    ->required()
                    ->options([
                        'overdue_summary'   => 'Tổng hợp quá hạn',
                        'upcoming_payments' => 'Sắp đến hạn (30 ngày tới)',
                    ]),

                Forms\Components\Toggle::make('is_active')
                    ->label('Kích hoạt')
                    ->default(true)
                    ->inline(false),
            ])->columns(3),

            Forms\Components\Section::make('Lịch gửi')->schema([
                Forms\Components\Select::make('frequency')
                    ->label('Tần suất')
                    ->required()
                    ->options([
                        'daily'   => 'Hàng ngày',
                        'weekly'  => 'Hàng tuần',
                        'monthly' => 'Hàng tháng',
                    ])
                    ->default('daily')
                    ->live(),

                Forms\Components\TimePicker::make('send_time')
                    ->label('Giờ gửi')
                    ->required()
                    ->default('08:00')
                    ->seconds(false),

                Forms\Components\Select::make('send_day')
                    ->label('Ngày gửi')
                    ->visible(fn (Get $get) => in_array($get('frequency'), ['weekly', 'monthly']))
                    ->options(fn (Get $get) => match ($get('frequency')) {
                        'weekly'  => [
                            1 => 'Thứ 2',
                            2 => 'Thứ 3',
                            3 => 'Thứ 4',
                            4 => 'Thứ 5',
                            5 => 'Thứ 6',
                            6 => 'Thứ 7',
                            7 => 'Chủ nhật',
                        ],
                        'monthly' => collect(range(1, 28))->mapWithKeys(fn ($d) => [$d => "Ngày {$d}"])->toArray(),
                        default   => [],
                    })
                    ->required(fn (Get $get) => in_array($get('frequency'), ['weekly', 'monthly'])),
            ])->columns(3),

            Forms\Components\Section::make('Người nhận')->schema([
                Forms\Components\Repeater::make('recipients')
                    ->label('')
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->label('Loại')
                            ->options([
                                'role' => 'Nhóm (Role)',
                                'user' => 'Người dùng cụ thể',
                            ])
                            ->required()
                            ->live()
                            ->columnSpan(1),

                        Forms\Components\Select::make('value')
                            ->label('Chọn')
                            ->required()
                            ->options(fn (Get $get) => match ($get('type')) {
                                'role' => [
                                    'ceo'             => 'CEO',
                                    'coo'             => 'COO',
                                    'vice_ceo'        => 'Vice CEO',
                                    'finance_manager' => 'Finance Manager',
                                    'finance_staff'   => 'Finance Staff',
                                ],
                                'user' => User::orderBy('name')->pluck('name', 'id')->toArray(),
                                default => [],
                            })
                            ->searchable()
                            ->columnSpan(2),
                    ])
                    ->columns(3)
                    ->addActionLabel('Thêm người nhận')
                    ->defaultItems(1)
                    ->minItems(1)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Tên báo cáo')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('report_type')
                    ->label('Loại')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'overdue_summary'   => 'Quá hạn',
                        'upcoming_payments' => 'Sắp đến hạn',
                        default             => $state,
                    })
                    ->color(fn ($state) => match ($state) {
                        'overdue_summary'   => 'danger',
                        'upcoming_payments' => 'warning',
                        default             => 'gray',
                    }),

                Tables\Columns\TextColumn::make('frequency')
                    ->label('Tần suất')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'daily'   => 'Hàng ngày',
                        'weekly'  => 'Hàng tuần',
                        'monthly' => 'Hàng tháng',
                        default   => $state,
                    }),

                Tables\Columns\TextColumn::make('send_time')
                    ->label('Giờ gửi'),

                Tables\Columns\TextColumn::make('recipients')
                    ->label('Người nhận')
                    ->state(fn ($record) => collect($record->recipients)
                        ->map(fn ($r) => $r['type'] === 'role'
                            ? 'Role: ' . $r['value']
                            : 'User #' . $r['value'])
                        ->join(', '))
                    ->wrap(),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Kích hoạt'),

                Tables\Columns\TextColumn::make('last_sent_at')
                    ->label('Gửi lần cuối')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Chưa gửi')
                    ->color('gray'),
            ])
            ->actions([
                Tables\Actions\Action::make('send_now')
                    ->label('Gửi ngay')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Xác nhận gửi báo cáo')
                    ->modalDescription('Báo cáo sẽ được gửi ngay đến tất cả người nhận đã cấu hình.')
                    ->action(function ($record) {
                        $service = new \App\Console\Commands\SendReportSubscriptionsCommand();
                        // Resolve recipients and send directly
                        $recipients = $record->resolveRecipientUsers();
                        if ($recipients->isEmpty()) {
                            \Filament\Notifications\Notification::make()
                                ->warning()
                                ->title('Không có người nhận')
                                ->send();
                            return;
                        }
                        $buildMail = match ($record->report_type) {
                            'overdue_summary' => new \App\Mail\OverdueSummaryMail(
                                \App\Models\PaymentSchedule::with(['contract.customer', 'responsibleUser'])
                                    ->where('status', 'overdue')->orderBy('due_date')->get(),
                                $record->name
                            ),
                            'upcoming_payments' => new \App\Mail\UpcomingPaymentsMail(
                                \App\Models\PaymentSchedule::with(['contract.customer', 'responsibleUser'])
                                    ->whereIn('status', ['pending', 'invoiced', 'partially_paid'])
                                    ->whereBetween('due_date', [today(), today()->addDays(30)])
                                    ->orderBy('due_date')->get(),
                                $record->name
                            ),
                            default => null,
                        };
                        if ($buildMail) {
                            foreach ($recipients as $user) {
                                \Illuminate\Support\Facades\Mail::to($user->email)->send($buildMail);
                            }
                            $record->update(['last_sent_at' => now()]);
                        }
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Đã gửi đến ' . $recipients->count() . ' người nhận')
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasRole(['ceo', 'coo']);
    }

    public static function canCreate(): bool
    {
        return auth()->user()->hasRole(['ceo', 'coo']);
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasRole(['ceo', 'coo']);
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasRole(['ceo', 'coo']);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListReportSubscriptions::route('/'),
            'create' => Pages\CreateReportSubscription::route('/create'),
            'edit'   => Pages\EditReportSubscription::route('/{record}/edit'),
        ];
    }
}
