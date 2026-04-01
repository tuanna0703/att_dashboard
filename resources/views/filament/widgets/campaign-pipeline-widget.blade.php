<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Workflow Campaign Pipeline</x-slot>

        <div class="overflow-x-auto">
            <div class="flex items-start gap-0 min-w-max py-2">

                @foreach ($steps as $index => $step)
                    {{-- Step card --}}
                    <div class="flex flex-col items-center w-36">
                        <a href="{{ $step['url'] }}" class="group flex flex-col items-center w-full">
                            <div @class([
                                'w-12 h-12 rounded-full flex items-center justify-center text-white font-bold text-lg shadow',
                                'bg-gray-300' => $step['count'] === 0,
                                'bg-blue-500' => $step['count'] > 0 && !$step['urgent'],
                                'bg-red-500 animate-pulse' => $step['urgent'],
                            ])>
                                {{ $step['count'] }}
                            </div>
                            <span class="mt-2 text-xs font-semibold text-center text-gray-700 dark:text-gray-300 leading-tight group-hover:text-primary-600">
                                {{ $step['label'] }}
                            </span>
                            @if ($step['sub'])
                                <span class="text-xs text-gray-400 text-center leading-tight mt-0.5">
                                    {{ $step['sub'] }}
                                </span>
                            @endif
                        </a>
                    </div>

                    {{-- Arrow between steps --}}
                    @if (!$loop->last)
                        <div class="flex items-center pt-3">
                            <div class="h-0.5 w-6 bg-gray-300"></div>
                            <x-heroicon-s-chevron-right class="w-4 h-4 text-gray-400 -ml-1" />
                        </div>
                    @endif
                @endforeach

            </div>
        </div>

        <div class="mt-3 flex flex-wrap gap-3 text-xs text-gray-500">
            <span class="flex items-center gap-1">
                <span class="inline-block w-3 h-3 rounded-full bg-gray-300"></span> Không có
            </span>
            <span class="flex items-center gap-1">
                <span class="inline-block w-3 h-3 rounded-full bg-blue-500"></span> Đang có
            </span>
            <span class="flex items-center gap-1">
                <span class="inline-block w-3 h-3 rounded-full bg-red-500"></span> Cần xử lý gấp
            </span>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
