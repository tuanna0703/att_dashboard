<div class="rounded-xl border border-gray-200 dark:border-white/10 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-white/5">
            <tr>
                <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Tên Campaign
                </th>
                <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Trạng thái
                </th>
                <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Khách hàng
                </th>
                <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Ngân sách
                </th>
                <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Sale
                </th>
                <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    AdOps
                </th>
            </tr>
        </thead>
        <tbody>
            <tr class="border-t border-gray-200 dark:border-white/10">
                <td class="px-4 py-3">
                    <div class="font-semibold text-base text-gray-900 dark:text-white">
                        {{ $record->campaign_name }}
                    </div>
                    <div class="text-xs text-gray-400 mt-0.5">{{ $record->brief_no }}</div>
                </td>
                <td class="px-4 py-3">
                    @php
                        $statusLabels = \App\Models\Brief::$statuses;
                        $statusColors = \App\Models\Brief::$statusColors;
                        $color = $statusColors[$record->status] ?? 'gray';
                        $label = $statusLabels[$record->status] ?? $record->status;
                    @endphp
                    <x-filament::badge :color="$color">{{ $label }}</x-filament::badge>
                </td>
                <td class="px-4 py-3 text-gray-900 dark:text-white">
                    {{ $record->customer?->name ?? '—' }}
                </td>
                <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">
                    {{ $record->budget ? number_format((float) $record->budget, 0, ',', '.') . ' ' . ($record->currency ?? 'VND') : '—' }}
                </td>
                <td class="px-4 py-3 text-gray-900 dark:text-white">
                    {{ $record->sale?->name ?? '—' }}
                </td>
                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">
                    {{ $record->adops?->name ?? 'Chưa assign' }}
                </td>
            </tr>
        </tbody>
    </table>
</div>
