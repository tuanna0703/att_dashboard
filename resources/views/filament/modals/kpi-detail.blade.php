<div class="overflow-x-auto px-1 pb-2">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-200 dark:border-white/10">
                <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Est Impression
                </th>
                <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Est Impression/Day
                </th>
                <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Est Ad Spot
                </th>
            </tr>
        </thead>
        <tbody>
            <tr class="border-b border-gray-100 dark:border-white/5">
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                    {{ $record->est_impression ? number_format($record->est_impression, 0, ',', '.') : '—' }}
                </td>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                    {{ $record->est_impression_day ? number_format($record->est_impression_day, 0, ',', '.') : '—' }}
                </td>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                    {{ $record->est_ad_spot ? number_format($record->est_ad_spot, 0, ',', '.') : '—' }}
                </td>
            </tr>
        </tbody>
    </table>
</div>
