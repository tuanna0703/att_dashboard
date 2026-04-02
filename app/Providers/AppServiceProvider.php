<?php

namespace App\Providers;

use App\Models\AppSetting;
use App\Models\Booking;
use App\Models\BookingLineItem;
use App\Models\CampaignTraffic;
use App\Models\CreativeSubmission;
use App\Models\ExpenseItem;
use App\Models\ReceiptAllocation;
use App\Observers\BookingLineItemObserver;
use App\Observers\BookingObserver;
use App\Observers\CampaignTrafficObserver;
use App\Observers\CreativeSubmissionObserver;
use App\Observers\ExpenseItemObserver;
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
        ExpenseItem::observe(ExpenseItemObserver::class);

        // ── New module observers ──────────────────────────────────────────────
        Booking::observe(BookingObserver::class);
        BookingLineItem::observe(BookingLineItemObserver::class);
        CreativeSubmission::observe(CreativeSubmissionObserver::class);
        CampaignTraffic::observe(CampaignTrafficObserver::class);

        $this->applyMailgunSettings();
    }

    private function applyMailgunSettings(): void
    {
        try {
            if (!Schema::hasTable('app_settings')) {
                return;
            }

            $driver      = AppSetting::get('mail.driver');
            $fromAddress = AppSetting::get('mail.from_address');
            $fromName    = AppSetting::get('mail.from_name', config('app.name'));

            if (!$driver) {
                return;
            }

            $base = [
                'mail.from.address' => $fromAddress ?: config('mail.from.address'),
                'mail.from.name'    => $fromName,
            ];

            if ($driver === 'mailgun') {
                $domain   = AppSetting::get('mailgun.domain');
                $secret   = AppSetting::get('mailgun.secret');
                $endpoint = AppSetting::get('mailgun.endpoint', 'api.mailgun.net');

                if ($domain && $secret) {
                    config(array_merge($base, [
                        'mail.default'              => 'mailgun',
                        'services.mailgun.domain'   => $domain,
                        'services.mailgun.secret'   => $secret,
                        'services.mailgun.endpoint' => $endpoint,
                    ]));
                }
            } elseif ($driver === 'smtp') {
                $host       = AppSetting::get('mail.smtp_host');
                $port       = AppSetting::get('mail.smtp_port', '587');
                $encryption = AppSetting::get('mail.smtp_encryption', 'tls');
                $username   = AppSetting::get('mail.smtp_username');
                $password   = AppSetting::get('mail.smtp_password');

                if ($host && $username) {
                    config(array_merge($base, [
                        'mail.default'                 => 'smtp',
                        'mail.mailers.smtp.host'       => $host,
                        'mail.mailers.smtp.port'       => $port,
                        'mail.mailers.smtp.encryption' => $encryption === 'none' ? null : $encryption,
                        'mail.mailers.smtp.username'   => $username,
                        'mail.mailers.smtp.password'   => $password,
                    ]));
                }
            }
        } catch (\Throwable) {
            // Bỏ qua nếu DB chưa sẵn sàng (vd: migrate lần đầu)
        }
    }
}
