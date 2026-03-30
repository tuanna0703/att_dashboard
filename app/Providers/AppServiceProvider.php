<?php

namespace App\Providers;

use App\Models\AppSetting;
use App\Models\ReceiptAllocation;
use App\Observers\ReceiptAllocationObserver;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        ReceiptAllocation::observe(ReceiptAllocationObserver::class);
        $this->applyMailgunSettings();
    }

    private function applyMailgunSettings(): void
    {
        try {
            if (!Schema::hasTable('app_settings')) {
                return;
            }
            $domain   = AppSetting::get('mailgun.domain');
            $secret   = AppSetting::get('mailgun.secret');
            $endpoint = AppSetting::get('mailgun.endpoint', 'api.mailgun.net');
            $from     = AppSetting::get('mailgun.from_address');
            $name     = AppSetting::get('mailgun.from_name', config('app.name'));

            if ($domain && $secret) {
                config([
                    'mail.default'                          => 'mailgun',
                    'services.mailgun.domain'               => $domain,
                    'services.mailgun.secret'               => $secret,
                    'services.mailgun.endpoint'             => $endpoint,
                    'mail.from.address'                     => $from ?: config('mail.from.address'),
                    'mail.from.name'                        => $name,
                ]);
            }
        } catch (\Throwable) {
            // Bỏ qua nếu DB chưa sẵn sàng (vd: migrate lần đầu)
        }
    }
}
