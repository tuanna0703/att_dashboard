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
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Mail;

class MailgunSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-envelope';
    protected static ?string $navigationLabel = 'Cấu hình Email';
    protected static ?string $title           = 'Cấu hình gửi Email';
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
            'driver'            => AppSetting::get('mail.driver', 'mailgun'),
            // Mailgun API
            'mg_domain'         => AppSetting::get('mailgun.domain'),
            'mg_secret'         => AppSetting::get('mailgun.secret'),
            'mg_endpoint'       => AppSetting::get('mailgun.endpoint', 'api.mailgun.net'),
            // SMTP
            'smtp_host'         => AppSetting::get('mail.smtp_host', 'smtp.mailgun.org'),
            'smtp_port'         => AppSetting::get('mail.smtp_port', '587'),
            'smtp_encryption'   => AppSetting::get('mail.smtp_encryption', 'tls'),
            'smtp_username'     => AppSetting::get('mail.smtp_username'),
            'smtp_password'     => AppSetting::get('mail.smtp_password'),
            // Người gửi
            'from_address'      => AppSetting::get('mail.from_address'),
            'from_name'         => AppSetting::get('mail.from_name', config('app.name')),
            'company_name'      => AppSetting::get('mail.company_name', config('app.name')),
            'footer_text'       => AppSetting::get('mail.footer_text'),
            'logo_url'          => AppSetting::get('mail.logo_url'),
            'logo_upload'       => null,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Phương thức gửi mail')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->schema([
                        Select::make('driver')
                            ->label('Driver')
                            ->options([
                                'mailgun' => 'Mailgun API',
                                'smtp'    => 'SMTP',
                            ])
                            ->default('mailgun')
                            ->required()
                            ->live(),
                    ]),

                Section::make('Mailgun API')
                    ->description('Lấy thông tin tại Mailgun → Sending → Domains → API Keys')
                    ->icon('heroicon-o-cloud')
                    ->visible(fn (Get $get) => $get('driver') === 'mailgun')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('mg_domain')
                                ->label('Mailgun Domain')
                                ->placeholder('mg.yourdomain.com')
                                ->maxLength(255),
                            Select::make('mg_endpoint')
                                ->label('API Endpoint')
                                ->options([
                                    'api.mailgun.net'    => 'api.mailgun.net (US)',
                                    'api.eu.mailgun.net' => 'api.eu.mailgun.net (EU)',
                                ])
                                ->default('api.mailgun.net'),
                            TextInput::make('mg_secret')
                                ->label('API Key (Private)')
                                ->placeholder('key-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx')
                                ->password()
                                ->revealable()
                                ->maxLength(255)
                                ->columnSpanFull(),
                        ]),
                    ]),

                Section::make('SMTP')
                    ->description('Cấu hình SMTP — Mailgun SMTP host: smtp.mailgun.org')
                    ->icon('heroicon-o-server')
                    ->visible(fn (Get $get) => $get('driver') === 'smtp')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('smtp_host')
                                ->label('SMTP Host')
                                ->placeholder('smtp.mailgun.org')
                                ->maxLength(255),
                            TextInput::make('smtp_port')
                                ->label('Port')
                                ->placeholder('587')
                                ->numeric(),
                            Select::make('smtp_encryption')
                                ->label('Mã hoá')
                                ->options([
                                    'tls'  => 'TLS (587)',
                                    'ssl'  => 'SSL (465)',
                                    'none' => 'Không mã hoá',
                                ])
                                ->default('tls'),
                            TextInput::make('smtp_username')
                                ->label('SMTP Username')
                                ->placeholder('postmaster@mg.yourdomain.com')
                                ->maxLength(255),
                            TextInput::make('smtp_password')
                                ->label('SMTP Password')
                                ->password()
                                ->revealable()
                                ->maxLength(255)
                                ->columnSpanFull(),
                        ]),
                    ]),

                Section::make('Thông tin người gửi')
                    ->description('Tên và địa chỉ email hiển thị khi người nhận thấy email')
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
                                ->label('Tên hiển thị (From name)')
                                ->placeholder('ATT Dashboard')
                                ->required()
                                ->maxLength(255),
                        ]),
                    ]),

                Section::make('Template email')
                    ->description('Tuỳ chỉnh logo, tên công ty và footer trong email gửi đi')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        \Filament\Forms\Components\FileUpload::make('logo_upload')
                            ->label('Logo công ty')
                            ->image()
                            ->directory('mail-assets')
                            ->disk('public')
                            ->imagePreviewHeight('60')
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/svg+xml', 'image/webp'])
                            ->helperText('PNG/JPG/SVG, khuyến nghị chiều cao 80px. Sẽ hiển thị ở header email thay cho tên công ty.')
                            ->afterStateUpdated(function ($state, \Filament\Forms\Set $set) {
                                if ($state) {
                                    $set('logo_url', asset('storage/' . $state));
                                }
                            })
                            ->live()
                            ->columnSpanFull(),

                        Grid::make(2)->schema([
                            TextInput::make('company_name')
                                ->label('Tên công ty')
                                ->placeholder('ATT Company')
                                ->required()
                                ->helperText('Hiển thị ở header (khi không có logo) và footer email.')
                                ->maxLength(255),
                            TextInput::make('logo_url')
                                ->label('Logo URL (tuỳ chọn)')
                                ->placeholder('https://yourdomain.com/images/logo.png')
                                ->url()
                                ->helperText('Điền URL nếu không upload. Upload ở trên sẽ tự điền trường này.')
                                ->maxLength(500),
                        ]),

                        \Filament\Forms\Components\Textarea::make('footer_text')
                            ->label('Văn bản footer')
                            ->placeholder("© 2026 ATT Company. Bảo lưu mọi quyền.\nĐịa chỉ: 123 Đường ABC, TP.HCM")
                            ->rows(3)
                            ->helperText('Để trống để dùng footer mặc định. Hỗ trợ xuống dòng.')
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        AppSetting::set('mail.driver', $data['driver']);
        AppSetting::set('mail.from_address', $data['from_address']);
        AppSetting::set('mail.from_name', $data['from_name']);
        AppSetting::set('mail.company_name', $data['company_name']);
        AppSetting::set('mail.footer_text', $data['footer_text'] ?? '');
        // logo_url: nếu vừa upload thì đã được set qua afterStateUpdated, lưu trực tiếp
        if (!empty($data['logo_url'])) {
            AppSetting::set('mail.logo_url', $data['logo_url']);
        }

        if ($data['driver'] === 'mailgun') {
            AppSetting::set('mailgun.domain', $data['mg_domain']);
            AppSetting::set('mailgun.secret', $data['mg_secret']);
            AppSetting::set('mailgun.endpoint', $data['mg_endpoint']);
        } else {
            AppSetting::set('mail.smtp_host', $data['smtp_host']);
            AppSetting::set('mail.smtp_port', $data['smtp_port']);
            AppSetting::set('mail.smtp_encryption', $data['smtp_encryption']);
            AppSetting::set('mail.smtp_username', $data['smtp_username']);
            AppSetting::set('mail.smtp_password', $data['smtp_password']);
        }

        Notification::make()
            ->title('Đã lưu cấu hình email')
            ->success()
            ->send();
    }

    public function sendTest(): void
    {
        $data = $this->form->getState();

        try {
            $this->applyConfigFromData($data);

            Mail::raw(
                'Email test từ ATT Dashboard — cấu hình gửi mail hoạt động.',
                fn ($msg) => $msg
                    ->to(auth()->user()->email)
                    ->subject('[ATT] Test email')
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

    private function applyConfigFromData(array $data): void
    {
        config([
            'mail.from.address' => $data['from_address'],
            'mail.from.name'    => $data['from_name'],
        ]);

        if ($data['driver'] === 'mailgun') {
            config([
                'mail.default'              => 'mailgun',
                'services.mailgun.domain'   => $data['mg_domain'],
                'services.mailgun.secret'   => $data['mg_secret'],
                'services.mailgun.endpoint' => $data['mg_endpoint'],
            ]);
        } else {
            config([
                'mail.default'                           => 'smtp',
                'mail.mailers.smtp.host'                 => $data['smtp_host'],
                'mail.mailers.smtp.port'                 => $data['smtp_port'],
                'mail.mailers.smtp.encryption'           => $data['smtp_encryption'] === 'none' ? null : $data['smtp_encryption'],
                'mail.mailers.smtp.username'             => $data['smtp_username'],
                'mail.mailers.smtp.password'             => $data['smtp_password'],
            ]);
        }
    }
}
