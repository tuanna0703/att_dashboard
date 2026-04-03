<div class="flex items-start justify-between gap-6 rounded-xl border border-gray-200 bg-white px-6 py-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
    <div class="min-w-0">
        <p class="mb-1 text-sm font-medium text-gray-500 dark:text-gray-400">
            {{ $record->customer?->name ?? '—' }}
        </p>
        <h2 class="truncate text-2xl font-bold text-gray-900 dark:text-white">
            {{ $record->campaign_name }}
        </h2>
        <p class="mt-1 text-xs text-gray-400">{{ $record->brief_no }}</p>
    </div>
    <div class="shrink-0 pt-1">
        @php
            $color = \App\Models\Brief::$statusColors[$record->status] ?? 'gray';
            $label = \App\Models\Brief::$statuses[$record->status] ?? $record->status;
        @endphp
        <x-filament::badge :color="$color">{{ $label }}</x-filament::badge>
    </div>
</div>
