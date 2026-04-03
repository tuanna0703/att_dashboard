@php
    $items     = $record->briefLineItems;
    $startDate = $items->count() ? $items->min('start_date') : null;
    $endDate   = $items->count() ? $items->max('end_date') : null;
    $duration  = ($startDate && $endDate)
        ? \Carbon\Carbon::parse($startDate)->diffInDays(\Carbon\Carbon::parse($endDate)) + 1
        : null;

    $stats = [
        [
            'label'     => 'Budget',
            'value'     => $record->budget
                ? number_format((float) $record->budget, 0, ',', '.') . ' ' . ($record->currency ?? 'VND')
                : '—',
            'icon'      => 'heroicon-o-banknotes',
            'highlight' => true,
        ],
        [
            'label' => 'Timeline',
            'value' => $startDate && $endDate
                ? \Carbon\Carbon::parse($startDate)->format('d/m/Y') . ' → ' . \Carbon\Carbon::parse($endDate)->format('d/m/Y')
                : '—',
            'icon'  => 'heroicon-o-calendar-days',
        ],
        [
            'label' => 'Duration',
            'value' => $duration ? $duration . ' ngày' : '—',
            'icon'  => 'heroicon-o-clock',
        ],
    ];
@endphp

<div class="grid grid-cols-3 gap-4">
    @foreach ($stats as $stat)
        <div class="rounded-xl border border-gray-200 bg-white px-5 py-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="mb-2 flex items-center gap-2">
                <x-dynamic-component :component="$stat['icon']" class="h-4 w-4 text-gray-400" />
                <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    {{ $stat['label'] }}
                </span>
            </div>
            <div class="text-xl font-bold {{ ($stat['highlight'] ?? false) ? 'text-primary-600 dark:text-primary-400' : 'text-gray-900 dark:text-white' }}">
                {{ $stat['value'] }}
            </div>
        </div>
    @endforeach
</div>
