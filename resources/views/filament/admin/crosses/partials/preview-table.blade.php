@php
    use Illuminate\Support\Number;
    $filteredStores = $this->getFilteredPreviewStores();
    $totalPreview = collect($filteredStores)->sum('total');
@endphp

<div class="space-y-4">
    {{-- BARRA DE HERRAMIENTAS SIMPLIFICADA --}}
    <div
        class="flex flex-col md:flex-row gap-4 items-center bg-gray-50 dark:bg-gray-800/50 p-4 rounded-lg border border-gray-200 dark:border-gray-700">

        <div class="flex-1 w-full">
            <div class="w-full">
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Buscar</label>
                <div class="space-y-2">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <x-heroicon-o-magnifying-glass class="h-4 w-4 text-gray-400" />
                        </div>
                        <input type="text" wire:model.live.debounce.300ms="previewSearch"
                            placeholder="Nombre, código idpos..."
                            class="block w-full pl-10 text-sm rounded-lg border-gray-300 bg-white dark:bg-gray-900 dark:border-gray-600 focus:border-primary-500 focus:ring-primary-500 dark:text-white shadow-sm">
                    </div>
                </div>
            </div>
        </div>

        {{-- Botón Exportar y Total --}}
        <div class="flex flex-col md:flex-row items-center gap-4 w-full md:w-auto">
            <div class="text-center md:text-right w-full md:w-auto">
                <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total a Liquidar</div>
                <div class="text-lg font-bold text-primary-600 dark:text-primary-400 whitespace-nowrap">
                    {{ Number::currency($this->previewTotalAmount, 'COP') }}
                </div>
            </div>

            <div class="flex flex-col gap-2 w-full md:w-auto">
                <x-filament::button color="success" icon="heroicon-o-arrow-down-tray" wire:click="exportPreview"
                    class="w-full md:w-auto">
                    Exportar Resumen
                </x-filament::button>

                <x-filament::button color="gray" size="sm" icon="heroicon-o-table-cells"
                    wire:click="exportPreviewDetails" class="w-full md:w-auto">
                    Exportar Detalle Completo
                </x-filament::button>
            </div>
        </div>
    </div>

    {{-- TABLA CON HEADERS CLICABLES --}}
    <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-sm">
            <thead class="bg-gray-50 dark:bg-gray-900/50 select-none">
                <tr>
                    <th wire:click="toggleSort('idpos')"
                        class="px-4 py-3 text-left cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800 transition group">
                        <div
                            class="flex items-center gap-1 font-bold text-gray-700 dark:text-gray-200 uppercase tracking-wider text-xs">
                            CÓDIGO
                            <x-filament::icon :icon="$this->previewSort === 'idpos_asc' ? 'heroicon-m-chevron-up' : ($this->previewSort === 'idpos_desc' ? 'heroicon-m-chevron-down' : 'heroicon-m-chevron-up-down')"
                                class="h-3 w-3 {{ str_starts_with($this->previewSort, 'idpos') ? 'text-primary-600' : 'text-gray-400 opacity-50' }}" />
                        </div>
                    </th>
                    <th wire:click="toggleSort('name')"
                        class="px-4 py-3 text-left cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800 transition group">
                        <div
                            class="flex items-center gap-1 font-bold text-gray-700 dark:text-gray-200 uppercase tracking-wider text-xs">
                            NOMBRE
                            <x-filament::icon :icon="$this->previewSort === 'name_asc' ? 'heroicon-m-chevron-up' : ($this->previewSort === 'name_desc' ? 'heroicon-m-chevron-down' : 'heroicon-m-chevron-up-down')"
                                class="h-3 w-3 {{ str_starts_with($this->previewSort, 'name') ? 'text-primary-600' : 'text-gray-400 opacity-50' }}" />
                        </div>
                    </th>
                    <th wire:click="toggleSort('total')"
                        class="px-4 py-3 text-right cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800 transition group">
                        <div
                            class="flex items-center justify-end gap-1 font-bold text-gray-700 dark:text-gray-200 uppercase tracking-wider text-xs">
                            VALOR
                            <x-filament::icon :icon="$this->previewSort === 'total_asc' ? 'heroicon-m-chevron-up' : ($this->previewSort === 'total_desc' ? 'heroicon-m-chevron-down' : 'heroicon-m-chevron-up-down')"
                                class="h-3 w-3 {{ str_starts_with($this->previewSort, 'total') ? 'text-primary-600' : 'text-gray-400 opacity-50' }}" />
                        </div>
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                @forelse($filteredStores as $store)
                    <!-- Fila Principal (Clickable) -->
                    <tr wire:click="toggleStoreDetails({{ $store['store_id'] }})"
                        class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 transition duration-150 {{ $this->expandedStoreId === $store['store_id'] ? 'bg-blue-50 dark:bg-blue-900/20' : '' }}">
                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300 font-mono text-xs">
                            <div class="flex items-center gap-2">
                                <x-filament::icon :icon="$this->expandedStoreId === $store['store_id'] ? 'heroicon-m-chevron-down' : 'heroicon-m-chevron-right'" class="h-4 w-4 text-gray-400" />
                                {{ $store['idpos'] }}
                            </div>
                        </td>
                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                            {{ $store['name'] }}
                            @if(($store['status'] ?? '') === 'liquidated')
                                <span
                                    class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 border border-green-200">
                                    Liquidada
                                </span>
                            @endif
                        </td>
                        <td
                            class="px-4 py-3 text-right font-semibold {{ $store['total'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-gray-100' }}">
                            {{ Number::currency($store['total'], 'COP') }}
                        </td>
                    </tr>

                    <!-- Fila Expandida (Detalle) -->
                    @if($this->expandedStoreId === $store['store_id'])
                        <tr class="bg-gray-50 dark:bg-gray-900/40">
                            <td colspan="3" class="px-4 py-4 border-b border-gray-100 dark:border-gray-800">
                                <div class="pl-6">
                                    <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Detalle de
                                        Simcards</h4>
                                    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-xs">
                                            <thead class="bg-gray-100 dark:bg-gray-800">
                                                <tr>
                                                    <th class="px-3 py-2 text-left font-medium text-gray-600">Simcard / Teléfono
                                                    </th>
                                                    <th class="px-3 py-2 text-center font-medium text-gray-600">% Com.</th>
                                                    <th class="px-3 py-2 text-right font-medium text-gray-600">Recarga Movilco
                                                    </th>
                                                    <th class="px-3 py-2 text-right font-medium text-gray-600">Base Liq. (Venta
                                                        - Rec)</th>
                                                    <th class="px-3 py-2 text-right font-medium text-gray-900">Total a Pagar
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody
                                                class="bg-white dark:bg-gray-900 divide-y divide-gray-100 dark:divide-gray-800">
                                                @foreach($store['lines'] as $line)
                                                    <tr>
                                                        <td class="px-3 py-2">
                                                            <div>{{ $line['iccid'] ?? 'Sin ICCID' }}</div>
                                                            <div class="text-xs text-gray-500">
                                                                {{ $line['phone_number'] ?? 'Sin Teléfono' }}
                                                            </div>
                                                        </td>
                                                        <td class="px-3 py-2 text-center">
                                                            {{ number_format($line['residual_percentage'], 2) }}%
                                                        </td>
                                                        <td class="px-3 py-2 text-right text-gray-600">
                                                            {{ Number::currency($line['movilco_recharge_amount'], 'COP') }}
                                                        </td>
                                                        <td class="px-3 py-2 text-right font-mono">
                                                            {{ Number::currency($line['base_liquidation_final'], 'COP') }}
                                                        </td>
                                                        <td class="px-3 py-2 text-right font-bold text-primary-600">
                                                            {{ Number::currency($line['pago_residual'], 'COP') }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="mt-2 text-xs text-gray-500">
                                        * Base Liq. = Monto Venta Claro - Recarga Movilco (mínimo 0)<br>
                                        * Total a Pagar = Base Liq. * % Com.
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="3" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                            <div class="flex flex-col items-center justify-center">
                                <x-heroicon-o-magnifying-glass class="w-12 h-12 text-gray-400 mb-3" />
                                <p class="text-lg font-medium">No se encontraron resultados</p>
                                <p class="text-sm">Intenta ajustar los filtros de búsqueda.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot class="bg-gray-100 dark:bg-gray-900/80 font-bold border-t border-gray-200 dark:border-gray-700">
                <tr>
                    <td colspan="3" class="px-4 py-3">
                        <div class="flex items-center justify-between w-full">
                            {{-- Controles de Paginación --}}
                            <div class="flex items-center gap-2">
                                <x-filament::button size="xs" color="gray" wire:click="previousPage"
                                    :disabled="$this->previewPage <= 1">
                                    <x-heroicon-m-chevron-left class="w-4 h-4" /> Anterior
                                </x-filament::button>

                                <span class="text-xs font-medium text-gray-600 dark:text-gray-400">
                                    Página {{ $this->previewPage }} de {{ $this->previewTotalPages }}
                                </span>

                                <x-filament::button size="xs" color="gray" wire:click="nextPage"
                                    :disabled="$this->previewPage >= $this->previewTotalPages">
                                    Siguiente <x-heroicon-m-chevron-right class="w-4 h-4" />
                                </x-filament::button>
                            </div>

                            {{-- Total --}}
                            <div class="flex items-center gap-4">
                                <span class="text-gray-900 dark:text-gray-100 uppercase text-xs tracking-wider">
                                    Total Filtrado
                                </span>
                                <span class="text-primary-700 dark:text-primary-400 text-lg">
                                    {{ Number::currency($totalPreview, 'COP') }}
                                </span>
                            </div>
                        </div>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>