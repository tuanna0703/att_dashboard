<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use App\Models\Brief;
use App\Models\Contract;
use App\Models\MediaBuyingOrder;
use App\Models\PaymentSchedule;
use App\Models\Receipt;
use Filament\Widgets\Widget;

class CampaignPipelineWidget extends Widget
{
    protected static ?int $sort = 10;
    protected static bool $isDiscovered = false;
    protected static string $view = 'filament.widgets.campaign-pipeline-widget';
    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $overduePayments = PaymentSchedule::where('status', 'overdue')->count();
        $pendingMBOs = MediaBuyingOrder::whereIn('status', [
            'pending_dept_head', 'dept_head_approved', 'pending_finance',
        ])->count();

        return [
            'steps' => [
                [
                    'label' => 'Brief',
                    'sub'   => 'Đang xử lý',
                    'count' => Brief::whereNotIn('status', ['converted', 'rejected'])->count(),
                    'url'   => '/admin/briefs',
                    'urgent' => false,
                ],
                [
                    'label' => 'Booking',
                    'sub'   => 'Chờ hợp đồng',
                    'count' => Booking::where('status', 'pending_contract')->count(),
                    'url'   => '/admin/bookings',
                    'urgent' => false,
                ],
                [
                    'label' => 'Hợp đồng',
                    'sub'   => 'Đang active',
                    'count' => Contract::where('status', 'active')->count(),
                    'url'   => '/admin/contracts',
                    'urgent' => false,
                ],
                [
                    'label' => 'Campaign chạy',
                    'sub'   => 'Campaign active',
                    'count' => Booking::where('status', 'campaign_active')->count(),
                    'url'   => '/admin/bookings?tableFilters[status][value]=campaign_active',
                    'urgent' => false,
                ],
                [
                    'label' => 'MBO',
                    'sub'   => 'Chờ duyệt',
                    'count' => $pendingMBOs,
                    'url'   => '/admin/media-buying-orders',
                    'urgent' => $pendingMBOs > 0,
                ],
                [
                    'label' => 'Nghiệm thu',
                    'sub'   => 'Chờ duyệt',
                    'count' => Booking::where('status', 'campaign_completed')->count(),
                    'url'   => '/admin/contracts',
                    'urgent' => false,
                ],
                [
                    'label' => 'Thanh toán',
                    'sub'   => 'Đợt quá hạn',
                    'count' => $overduePayments,
                    'url'   => '/admin/payment-schedules?tableFilters[status][value]=overdue',
                    'urgent' => $overduePayments > 0,
                ],
                [
                    'label' => 'Đã thu',
                    'sub'   => 'Tháng này',
                    'count' => Receipt::whereMonth('receipt_date', now()->month)
                        ->whereYear('receipt_date', now()->year)
                        ->count(),
                    'url'   => '/admin/receipts',
                    'urgent' => false,
                ],
            ],
        ];
    }
}
