<?php

namespace App\Notifications;

use App\Models\PaymentSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class OverduePaymentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Collection $overdueSchedules
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        $count = $this->overdueSchedules->count();
        $total = number_format($this->overdueSchedules->sum('amount'), 0, ',', '.');

        return [
            'title'   => "Có {$count} đợt thanh toán quá hạn",
            'body'    => "Tổng giá trị quá hạn: {$total} VND. Vui lòng theo dõi và xử lý.",
            'count'   => $count,
            'total'   => $this->overdueSchedules->sum('amount'),
            'schedules' => $this->overdueSchedules->map(fn (PaymentSchedule $s) => [
                'id'            => $s->id,
                'contract_code' => $s->contract->contract_code ?? '—',
                'customer'      => $s->contract->customer->name ?? '—',
                'installment'   => $s->installment_no,
                'amount'        => $s->amount,
                'due_date'      => $s->due_date?->format('d/m/Y'),
            ])->toArray(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = $this->overdueSchedules->count();
        $total = number_format($this->overdueSchedules->sum('amount'), 0, ',', '.');

        $mail = (new MailMessage)
            ->subject("[ATT Dashboard] {$count} đợt thanh toán quá hạn cần xử lý")
            ->greeting("Xin chào {$notifiable->name},")
            ->line("Bạn có **{$count} đợt thanh toán quá hạn** cần theo dõi.")
            ->line("**Tổng giá trị quá hạn: {$total} VND**")
            ->line('---');

        foreach ($this->overdueSchedules->take(10) as $s) {
            $mail->line(
                "• [{$s->contract->contract_code}] {$s->contract->customer->name} — " .
                "Đợt {$s->installment_no} — " .
                number_format($s->amount, 0, ',', '.') . " VND — HH: {$s->due_date?->format('d/m/Y')}"
            );
        }

        if ($this->overdueSchedules->count() > 10) {
            $mail->line('*(và ' . ($this->overdueSchedules->count() - 10) . ' đợt khác)*');
        }

        return $mail->action('Xem trên hệ thống', url('/admin/payment-schedules?tableFilters[status][value]=overdue'));
    }
}
