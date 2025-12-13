@php
    use Illuminate\Support\Number;
@endphp

<x-filament-panels::page>
    <div class="space-y-5">
        <x-filament::card>
            <div class="grid gap-4 md:grid-cols-3 items-end">
                <div class="space-y-2">
                    <label class="text-xs font-semibold text-slate-500">Filtrar Tienda</label>
                    <input type="text" wire:model.live.debounce.300ms="searchStore"
                        placeholder="Buscar por nombre o ID..."
                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 placeholder-slate-400">
                </div>

                <div class="space-y-2">
                    <label class="text-xs font-semibold text-slate-500">Seleccionar Tienda</label>
                    <select wire:model.live="storeId"
                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                        @if(empty($stores))
                            <option value="">Sin resultados</option>
                        @endif
                        @foreach ($stores as $store)
                            <option value="{{ $store['id'] }}">{{ $store['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-2">
                    <label class="text-xs font-semibold text-slate-500">Periodo (YYYY-MM)</label>
                    @if(!empty($periodOptions))
                        <select wire:model.live="period"
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
            </div>

            {{-- Indicador de carga --}}
            <div wire:loading wire:target="storeId,period,searchStore" class="mt-3">
                <div class="flex items-center gap-2 text-sm text-primary-600">
                    <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                        viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                        </circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                        </path>
                    </svg>
                    <span>Actualizando datos...</span>
                </div>
            </div>
        </x-filament::card>

        @if (!empty($summary))
            <div class="grid gap-4 md:grid-cols-3 lg:grid-cols-4">
                <x-filament::card>
                    <p class="text-xs font-semibold text-slate-500">Saldo inicial</p>
                    <p class="text-2xl font-bold">{{ Number::currency($summary['initial'] ?? 0, 'COP') }}</p>
                </x-filament::card>
                <x-filament::card>
                    <p class="text-xs font-semibold text-slate-500">Créditos (Liquidaciones)</p>
                    <p class="text-2xl font-bold text-emerald-600">{{ Number::currency($summary['credits'] ?? 0, 'COP') }}
                    </p>
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
                    <p class="text-xs font-semibold text-slate-500">Saldo que ve el tendero</p>
                    <p class="text-2xl font-bold">{{ Number::currency($summary['tender_balance'] ?? 0, 'COP') }}</p>
                    <p class="text-xs text-slate-500 mt-1">Diferencia:
                        {{ Number::currency($summary['difference'] ?? 0, 'COP') }}
                    </p>
                    @if ($summary['warn'] ?? false)
                        <x-filament::badge color="danger" class="mt-2">Diferencia detectada</x-filament::badge>
                    @endif
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
                            <th class="px-3 py-2 text-left font-semibold text-slate-600 uppercase text-xs">Descripción
                            </th>
                            <th class="px-3 py-2 text-right font-semibold text-slate-600 uppercase text-xs">Monto</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-600 uppercase text-xs">Origen</th>
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
                                <td class="px-3 py-2 text-xs text-slate-500">{{ $row['source_label'] ?? '' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-4 text-center text-sm text-slate-500">No hay movimientos en
                                    este periodo.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::card>
    </div>
</x-filament-panels::page>