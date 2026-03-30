<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class MailgunSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-envelope';
    protected static ?string $navigationLabel = 'Cấu hình Mailgun';
    protected static ?string $title           = 'Cấu hình Mailgun';
    protected static ?string $navigationGroup = 'Cài đặt';
    protected static ?int $navigationSort     = 20;
    protected static string $view             = 'filament.pages.mailgun-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('ceo') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'domain'       => AppSetting::get('mailgun.domain'),
            'secret'       => AppSetting::get('mailgun.secret'),
            'endpoint'     => AppSetting::get('mailgun.endpoint', 'api.mailgun.net'),
            'from_address' => AppSetting::get('mailgun.from_address'),
            'from_name'    => AppSetting::get('mailgun.from_name', config('app.name')),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Kết nối Mailgun API')
                    ->description('Thông tin kết nối lấy từ trang Mailgun → Sending → Domains')
                    ->icon('heroicon-o-cloud')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('domain')
                                ->label('Mailgun Domain')
                                ->placeholder('mg.yourdomain.com')
                                ->required()
                                ->maxLength(255),
                            Select::make('endpoint')
                                ->label('API Endpoint')
                                ->options([
                                    'api.mailgun.net'    => 'api.mailgun.net (US)',
                                    'api.eu.mailgun.net' => 'api.eu.mailgun.net (EU)',
                                ])
                                ->default('api.mailgun.net')
                                ->required(),
                            TextInput::make('secret')
                                ->label('API Key (Private)')
                                ->placeholder('key-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx')
                                ->password()
                                ->revealable()
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),
                        ]),
                    ]),

                Section::make('Thông tin người gửi')
                    ->description('Tên và địa chỉ email hiển thị khi nhận email từ hệ thống')
                    ->icon('heroicon-o-user-circle')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('from_address')
                                ->label('Email gửi đi')
                                ->email()
                                ->placeholder('noreply@yourdomain.com')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('from_name')
                                ->label('Tên hiển thị')
                                ->placeholder('ATT Dashboard')
                                ->required()
                                ->maxLength(255),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        AppSetting::setGroup('mailgun', $data);

        Notification::make()
            ->title('Đã lưu cấu hình Mailgun')
            ->success()
            ->send();
    }

    public function sendTest(): void
    {
        $data = $this->form->getState();

        if (empty($data['domain']) || empty($data['secret'])) {
            Notification::make()
                ->title('Vui lòng điền đầy đủ thông tin trước khi gửi thử')
                ->warning()
                ->send();
            return;
        }

        try {
            // Áp dụng config tạm để test
            config([
                'mail.default'              => 'mailgun',
                'services.mailgun.domain'   => $data['domain'],
                'services.mailgun.secret'   => $data['secret'],
                'services.mailgun.endpoint' => $data['endpoint'],
                'mail.from.address'         => $data['from_address'],
                'mail.from.name'            => $data['from_name'],
            ]);

            \Illuminate\Support\Facades\Mail::raw(
                'Email test từ ATT Dashboard — cấu hình Mailgun hoạt động.',
                fn ($msg) => $msg
                    ->to(auth()->user()->email)
                    ->subject('[ATT] Test email Mailgun')
            );

            Notification::make()
                ->title('Đã gửi email test đến ' . auth()->user()->email)
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Gửi thất bại: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }
}
