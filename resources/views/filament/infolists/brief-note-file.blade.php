<div class="rounded-xl border border-gray-200 dark:border-white/10 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-white/5">
            <tr>
                @if ($record->note)
                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 {{ $record->file_path ? 'w-2/3' : 'w-full' }}">
                        Ghi chú
                    </th>
                @endif
                @if ($record->file_path)
                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 {{ $record->note ? 'w-1/3' : 'w-full' }}">
                        File đính kèm
                    </th>
                @endif
            </tr>
        </thead>
        <tbody>
            <tr class="border-t border-gray-200 dark:border-white/10">
                @if ($record->note)
                    <td class="px-4 py-3 text-gray-900 dark:text-white whitespace-pre-line align-top">
                        {{ $record->note }}
                    </td>
                @endif
                @if ($record->file_path)
                    <td class="px-4 py-3 align-top">
                        <a href="{{ \Illuminate\Support\Facades\Storage::url($record->file_path) }}"
                           target="_blank"
                           class="inline-flex items-center gap-1.5 text-gray-500 hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-400 transition">
                            <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                            <span>{{ basename($record->file_path) }}</span>
                        </a>
                    </td>
                @endif
            </tr>
        </tbody>
    </table>
</div>
