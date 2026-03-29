<x-filament::dropdown placement="bottom-end">
    <x-slot name="trigger">
        <x-filament::button
            icon="heroicon-m-plus-circle"
            color="primary"
            size="sm"
        >
            Tạo nhanh
        </x-filament::button>
    </x-slot>

    <x-filament::dropdown.list>
        <x-filament::dropdown.list.item
            :href="route('filament.admin.resources.contracts.create')"
            icon="heroicon-o-document-text"
            tag="a"
        >
            Hợp đồng
        </x-filament::dropdown.list.item>

        <x-filament::dropdown.list.item
            :href="route('filament.admin.resources.payment-schedules.create')"
            icon="heroicon-o-calendar-days"
            tag="a"
        >
            Lịch thanh toán
        </x-filament::dropdown.list.item>

        <x-filament::dropdown.list.item
            :href="route('filament.admin.resources.invoices.create')"
            icon="heroicon-o-clipboard-document-list"
            tag="a"
        >
            Hóa đơn
        </x-filament::dropdown.list.item>

        <x-filament::dropdown.list.item
            :href="route('filament.admin.resources.receipts.create')"
            icon="heroicon-o-banknotes"
            tag="a"
        >
            Phiếu thu
        </x-filament::dropdown.list.item>
    </x-filament::dropdown.list>
</x-filament::dropdown>
