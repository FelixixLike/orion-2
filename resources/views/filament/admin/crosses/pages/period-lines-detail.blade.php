@php
    use Illuminate\Support\Number;
@endphp

<x-filament-panels::page>
    <div class="space-y-4">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold">Detalle por líneas</h1>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Periodo: {{ $period ?? 'N/D' }} @if($storeId) • Tienda ID {{ $storeId }} @endif
                    @if($usingPreview)
                        <x-filament::badge color="warning" class="ml-2">Preview (sin liquidaciones guardadas)</x-filament::badge>
                    @endif
                </p>
            </div>
            <x-filament::button wire:click="export" color="primary" icon="heroicon-o-arrow-down-tray">
                Exportar CSV
            </x-filament::button>
        </div>

        <x-filament::card>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-xs">
                    <thead class="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold text-gray-600 uppercase">Teléfono</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-600 uppercase">ICCID</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-600 uppercase">IDPOS</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-600 uppercase">Comisión Claro</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-600 uppercase">Carga periodo (Claro)</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-600 uppercase">Recarga Movilco</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-600 uppercase">Base Liq Final</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-600 uppercase">% Residual</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-600 uppercase">% Traslado</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-600 uppercase">Pago residual</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-600 uppercase">Estatus comisión</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-600 uppercase">F. activación</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-600 uppercase">F. corte</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-600 uppercase">CUSTCODE</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-600 uppercase">Periodo</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-600 uppercase">Tienda</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse ($linesPaginator?->items() ?? [] as $row)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/30">
                                <td class="px-3 py-2">{{ $row['phone_number'] }}</td>
                                <td class="px-3 py-2">{{ $row['iccid'] }}</td>
                                <td class="px-3 py-2">{{ $row['idpos'] }}</td>
                                <td class="px-3 py-2">{{ Number::currency($row['total_commission'] ?? 0, 'COP') }}</td>
                                <td class="px-3 py-2">{{ Number::currency($row['operator_total_recharge'] ?? 0, 'COP') }}</td>
                                <td class="px-3 py-2">{{ Number::currency($row['movilco_recharge_amount'] ?? 0, 'COP') }}</td>
                                <td class="px-3 py-2">{{ Number::currency($row['base_liquidation_final'] ?? 0, 'COP') }}</td>
                                <td class="px-3 py-2">{{ $row['residual_percentage'] ?? '0' }}%</td>
                                <td class="px-3 py-2">{{ $row['transfer_percentage'] ?? '0' }}%</td>
                                <td class="px-3 py-2">{{ Number::currency($row['residual_payment'] ?? 0, 'COP') }}</td>
                                <td class="px-3 py-2">{{ $row['commission_status'] }}</td>
                                <td class="px-3 py-2">{{ $row['activation_date'] }}</td>
                                <td class="px-3 py-2">{{ $row['cutoff_date'] }}</td>
                                <td class="px-3 py-2">{{ $row['custcode'] }}</td>
                                <td class="px-3 py-2">{{ $row['period'] }}</td>
                                <td class="px-3 py-2">{{ $row['store'] ?? $row['store_idpos'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="16" class="px-3 py-4 text-center text-sm text-gray-500 dark:text-gray-300">
                                    No hay líneas para el periodo seleccionado.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $linesPaginator?->links() }}
            </div>
        </x-filament::card>
    </div>
</x-filament-panels::page>
