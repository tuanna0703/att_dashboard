<x-mail::message>
# {{ $reportName }}

Báo cáo ngày **{{ now()->format('d/m/Y') }}** — dự báo 30 ngày tới (đến {{ now()->addDays(30)->format('d/m/Y') }})

---

## Tổng quan

| | |
|---|---|
| Số đợt sắp đến hạn | **{{ $scheduleCount }} đợt** |
| Tổng giá trị | **{{ number_format($totalAmount, 0, ',', '.') }} ₫** |

---

## Chi tiết các khoản sắp đến hạn

<x-mail::table>
| Khách hàng | Hợp đồng | Ngày đến hạn | Còn lại | Số tiền | Trạng thái | Phụ trách |
|---|---|---|---|---|---|---|
@foreach ($schedules as $s)
| {{ $s->contract->customer->name ?? '—' }} | {{ $s->contract->contract_code ?? '—' }} | {{ \Carbon\Carbon::parse($s->due_date)->format('d/m/Y') }} | {{ now()->diffInDays(\Carbon\Carbon::parse($s->due_date)) }} ngày | {{ number_format((float)$s->amount, 0, ',', '.') }} ₫ | {{ match($s->status) { 'pending' => 'Chờ', 'invoiced' => 'Đã HĐ', 'partially_paid' => 'Thu 1 phần', default => $s->status } }} | {{ $s->responsibleUser->name ?? '—' }} |
@endforeach
</x-mail::table>

---

<x-mail::button :url="config('app.url') . '/admin/payment-schedules?tableFilters[due_this_month][isActive]=true'">
Xem tất cả trên hệ thống
</x-mail::button>

Trân trọng,<br>
{{ config('app.name') }}
</x-mail::message>
