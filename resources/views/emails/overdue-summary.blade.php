<x-mail::message>
# {{ $reportName }}

Báo cáo ngày **{{ now()->format('d/m/Y') }}**

---

## Tổng quan

| | |
|---|---|
| Số đợt quá hạn | **{{ $overdueCount }} đợt** |
| Tổng tiền quá hạn | **{{ number_format($totalOverdue, 0, ',', '.') }} ₫** |

---

## Chi tiết các khoản quá hạn

<x-mail::table>
| Khách hàng | Hợp đồng | Ngày đến hạn | Quá hạn | Số tiền | Phụ trách |
|---|---|---|---|---|---|
@foreach ($schedules as $s)
| {{ $s->contract->customer->name ?? '—' }} | {{ $s->contract->contract_code ?? '—' }} | {{ \Carbon\Carbon::parse($s->due_date)->format('d/m/Y') }} | {{ \Carbon\Carbon::parse($s->due_date)->diffInDays(now()) }} ngày | {{ number_format((float)$s->amount, 0, ',', '.') }} ₫ | {{ $s->responsibleUser->name ?? '—' }} |
@endforeach
</x-mail::table>

---

<x-mail::button :url="config('app.url') . '/admin/payment-schedules?tableFilters[status][value]=overdue'" color="red">
Xem tất cả trên hệ thống
</x-mail::button>

Trân trọng,<br>
{{ config('app.name') }}
</x-mail::message>
