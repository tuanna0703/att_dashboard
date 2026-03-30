<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex items-center gap-3">
            <x-filament::button type="submit" icon="heroicon-m-check">
                Lưu cấu hình
            </x-filament::button>

            <x-filament::button
                type="button"
                wire:click="sendTest"
                color="gray"
                icon="heroicon-m-paper-airplane"
            >
                Gửi email test
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
