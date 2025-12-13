<x-filament-panels::page>
    @php
        $summary = $this->getSummaryData();
    @endphp

    <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
        <x-filament::section>
            <x-slot name="heading">
                Total Comisión
            </x-slot>

            <div class="text-center py-6">
                <div style="font-size: 2.5rem; font-weight: 700;" class="text-gray-950 dark:text-white">
                    {{ $summary['totalComission'] }} COP</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                Total Valor a Pagar
            </x-slot>

            <div class="text-center py-6">
                <div style="font-size: 2.5rem; font-weight: 700;" class="text-gray-950 dark:text-white">
                    {{ $summary['totalValorAPagar'] }} COP</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                Diferencia Total
            </x-slot>

            <div class="text-center py-6">
                <div style="font-size: 2.5rem; font-weight: 700; color: #dc2626;">{{ $summary['diferencia'] }} COP</div>
            </div>
        </x-filament::section>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
