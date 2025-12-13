@php
    use Illuminate\Support\Number;
@endphp

<x-filament-panels::page>
    <div class="space-y-5">
        <div class="space-y-1">
            <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100">Estado de cuenta de la tienda</h1>
            <p class="text-sm text-slate-600 dark:text-slate-300">
                Detalle de movimientos para la tienda seleccionada en el periodo elegido.
            </p>
        </div>

        <div class="flex flex-col gap-3 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950">
            <div class="grid gap-4 md:grid-cols-3 items-end">
                <div class="space-y-2">
                    <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Tienda</label>
                    <div
                        x-data="{
                            storeSearch: '',
                            stores: @js($stores ?? []),
                            get filteredStores() {
                                const query = (this.storeSearch || '').toLowerCase().trim()
                                let list = !query
                                    ? this.stores
                                    : this.stores.filter((s) => (s.label || '').toLowerCase().includes(query))

                                const selectedId = this.$wire.storeId
                                if (selectedId && !list.some((s) => s.id == selectedId)) {
                                    const selected = this.stores.find((s) => s.id == selectedId)
                                    if (selected) {
                                        list = [selected, ...list]
                                    }
                                }

                                return list
                            },
                        }"
                        class="space-y-2"
                    >
                        <input
                            type="text"
                            x-model.debounce.250ms="storeSearch"
                            placeholder="Buscar tienda..."
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                        />
                        <select
                            wire:model="storeId"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                        >
                            <template x-for="store in filteredStores" :key="store.id">
                                <option :value="store.id" x-text="store.label"></option>
                            </template>
                        </select>
                        <p x-show="filteredStores.length === 0" class="text-xs text-slate-500 dark:text-slate-400">
                            Sin resultados.
                        </p>
                    </div>
                </div>
                <div class="space-y-2">
                    <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Periodo (YYYY-MM)</label>
                    @if(!empty($periodOptions))
                        <select wire:model="period"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                            @foreach ($periodOptions as $periodOption)
                                <option value="{{ $periodOption }}">{{ $periodOption }}</option>
                            @endforeach
                        </select>
                    @else
                        <select disabled
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-400 shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-500">
                            <option>Sin movimientos</option>
                        </select>
                    @endif
                </div>
                <div class="flex justify-end">
                    <x-filament::button wire:click="export" color="primary" icon="heroicon-o-arrow-down-tray">
                        Exportar estado de cuenta (CSV)
                    </x-filament::button>
                </div>
            </div>
        </div>

        @if (!empty($summary))
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <x-filament::card>
                    <p class="text-xs font-semibold text-slate-500">Saldo inicial</p>
                    <p class="text-2xl font-bold">{{ Number::currency($summary['initial'] ?? 0, 'COP') }}</p>
                </x-filament::card>
                <x-filament::card>
                    <p class="text-xs font-semibold text-slate-500">Créditos (Liquidaciones)</p>
                    <p class="text-2xl font-bold text-emerald-600">{{ Number::currency($summary['credits'] ?? 0, 'COP') }}</p>
                </x-filament::card>
                <x-filament::card>
                    <p class="text-xs font-semibold text-slate-500">Débitos (Redenciones)</p>
                    <p class="text-2xl font-bold text-red-600">{{ Number::currency($summary['debits'] ?? 0, 'COP') }}</p>
                </x-filament::card>
                <x-filament::card>
                    <p class="text-xs font-semibold text-slate-500">Ajustes</p>
                    <p class="text-2xl font-bold">{{ Number::currency($summary['adjustments'] ?? 0, 'COP') }}</p>
                </x-filament::card>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <x-filament::card class="md:col-span-2">
                    <p class="text-xs font-semibold text-slate-500">Saldo final</p>
                    <p class="text-3xl font-bold">{{ Number::currency($summary['final'] ?? 0, 'COP') }}</p>
                </x-filament::card>
                <x-filament::card>
                    <p class="text-xs font-semibold text-slate-500">Tienda</p>
                    <p class="text-base font-semibold">{{ $summary['store_label'] ?? '' }}</p>
                </x-filament::card>
            </div>
        @endif

        <x-filament::card>
            <h2 class="text-lg font-semibold mb-3">Movimientos del periodo</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800 text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-900/50">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold text-slate-600 uppercase text-xs">Fecha</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-600 uppercase text-xs">Tipo</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-600 uppercase text-xs">Descripción</th>
                            <th class="px-3 py-2 text-right font-semibold text-slate-600 uppercase text-xs">Monto</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @forelse ($movements as $row)
                            @php $amount = $row['amount'] ?? 0; @endphp
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/40">
                                <td class="px-3 py-2">{{ $row['date'] ?? '' }}</td>
                                <td class="px-3 py-2">{{ $row['type_label'] ?? '' }}</td>
                                <td class="px-3 py-2">{{ $row['description'] ?? '' }}</td>
                                <td class="px-3 py-2 text-right {{ $amount >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                                    {{ Number::currency($amount, 'COP') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-3 py-4 text-center text-sm text-slate-500">No hay movimientos en este periodo.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::card>
    </div>
</x-filament-panels::page>
