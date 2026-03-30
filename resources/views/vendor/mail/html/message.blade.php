<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
@php $logoUrl = \App\Models\AppSetting::get('mail.logo_url'); @endphp
@if ($logoUrl)
<img src="{{ $logoUrl }}" alt="{{ \App\Models\AppSetting::get('mail.company_name', config('app.name')) }}" style="height: 40px; max-width: 200px; object-fit: contain;">
@else
{{ \App\Models\AppSetting::get('mail.company_name', config('app.name')) }}
@endif
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
@php $footerText = \App\Models\AppSetting::get('mail.footer_text'); @endphp
@if ($footerText)
{!! nl2br(e($footerText)) !!}
@else
© {{ date('Y') }} {{ \App\Models\AppSetting::get('mail.company_name', config('app.name')) }}. Bảo lưu mọi quyền.
@endif
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
