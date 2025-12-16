@php
    $periods = collect($this->periodOptions ?? []);
    $hasPeriods = $periods->isNotEmpty();
    $hasSelectedPeriod = filled($this->selectedPeriod);
    $summary = $this->summary ?? [];
    $fullColumns = $this->fullColumns ?? [];
    $fullRows = $this->fullRows ?? [];
    $perPageOptions = $this->perPageOptions ?? [10, 25, 50, 100];
    $currentPerPage = max((int) ($this->fullRowsPerPage ?? 25), 1);
    $displayRows = array_slice($fullRows, 0, $currentPerPage);
    $fullRowsTotal = $this->fullRowsTotal ?? count($fullRows);
    $hasRows = $fullRowsTotal > 0;
    $difference = $summary['difference'] ?? 0;
    $differenceState = $difference >= 0 ? 'positive' : 'negative';
    $rechargeRows = $this->rechargeRows ?? [];
    $rechargeRowsTotal = $this->rechargeRowsTotal ?? 0;
    $rechargeTotalAmount = $this->rechargeTotalAmount ?? 0;
    $currentRechargePerPage = max((int) ($this->rechargeRowsPerPage ?? 5), 1);
    $displayRechargeRows = array_slice($rechargeRows, 0, $currentRechargePerPage);
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <div
            class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between rounded-2xl border border-slate-200/70 bg-white/90 px-4 py-5 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
            <div>
                <h2 class="text-xl font-semibold text-slate-900 dark:text-white">Historico Pagos Claro</h2>
                <p class="text-sm text-slate-600 dark:text-slate-400">Selecciona un periodo importado para ver todos los
                    registros y calculos.</p>
            </div>
            <div class="w-full md:w-72">
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Periodo</label>
                @if($hasPeriods)
                    <select wire:model.live="selectedPeriod"
                        class="fi-input block w-full rounded-xl border-slate-200 bg-white/90 text-slate-900 shadow-sm transition focus:border-primary-500 focus:ring-primary-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                        @foreach($periods as $period)
                            <option value="{{ $period['value'] }}">{{ $period['label'] }}</option>
                        @endforeach
                    </select>
                @else
                    <select disabled
                        class="fi-input block w-full rounded-xl border-slate-200 bg-white/90 text-slate-400 shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-500">
                        <option>Sin periodos disponibles</option>
                    </select>
                @endif
            </div>
        </div>

        @if(!$hasPeriods)
            <div
                class="rounded-2xl border border-slate-200/70 bg-white/90 p-6 text-center text-sm text-slate-600 shadow-sm dark:border-slate-800 dark:bg-slate-900/70 dark:text-slate-300">
                No hay periodos importados todavía. Primero importa Pagos Claro o Recargas / Variables para habilitar este
                módulo.
            </div>
        @elseif(!$hasSelectedPeriod)
            <div
                class="rounded-2xl border border-slate-200/70 bg-white/90 p-6 text-center text-sm text-slate-600 shadow-sm dark:border-slate-800 dark:bg-slate-900/70 dark:text-slate-300">
                Selecciona un periodo para ver los registros.
            </div>
        @endif

        @if($hasSelectedPeriod && !empty($summary))
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3 lg:grid-cols-5">
                <div
                    class="rounded-2xl border border-slate-200/70 bg-white/90 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/70">
                    <div class="text-sm font-medium text-slate-500 dark:text-slate-400">Periodo (YYYY-MM)</div>
                    <div class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white">
                        {{ $summary['period'] ?? 'N/A' }}
                    </div>
                </div>
                <div
                    class="rounded-2xl border border-slate-200/70 bg-white/90 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/70">
                    <div class="text-sm font-medium text-slate-500 dark:text-slate-400">Filas cargadas</div>
                    <div class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white">
                        {{ $summary['total_rows'] ?? 0 }}
                    </div>
                </div>
                <div
                    class="rounded-2xl border border-slate-200/70 bg-white/90 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/70">
                    <div class="text-sm font-medium text-slate-500 dark:text-slate-400">Total pagado (80 + 20)</div>
                    <div class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white">
                        ${{ number_format($summary['total_paid'] ?? 0, 2, ',', '.') }}</div>
                </div>
                <div
                    class="rounded-2xl border border-slate-200/70 bg-white/90 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/70">
                    <div class="text-sm font-medium text-slate-500 dark:text-slate-400">Total calculo (Monto * %)</div>
                    <div class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white">
                        ${{ number_format($summary['total_calculated'] ?? 0, 2, ',', '.') }}</div>
                </div>
                <div
                    class="rounded-2xl p-4 shadow-sm lg:col-span-2 {{ $differenceState === 'positive' ? 'bg-emerald-50 text-emerald-800 border border-emerald-100 dark:bg-emerald-900/30 dark:text-emerald-200 dark:border-emerald-900/40' : 'bg-amber-50 text-amber-800 border border-amber-100 dark:bg-amber-900/30 dark:text-amber-100 dark:border-amber-900/40' }}">
                    <div class="text-sm font-medium">Diferencia (Pagado - Calculado)</div>
                    <div class="mt-1 text-3xl font-semibold">${{ number_format($difference, 2, ',', '.') }}</div>
                    <p class="text-xs mt-1 opacity-80">Un valor positivo significa que se pagó igual o más de lo calculado.
                    </p>
                </div>
                <div
                    class="rounded-2xl border border-slate-200/70 bg-white/90 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/70">
                    <div class="text-sm font-medium text-slate-500 dark:text-slate-400">Total recargas del periodo</div>
                    <div class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white">
                        ${{ number_format($rechargeTotalAmount, 2, ',', '.') }}
                    </div>
                    <p class="text-xs mt-1 text-slate-500 dark:text-slate-400">{{ $rechargeRowsTotal }} registro(s)</p>
                </div>
            </div>

            @if (($summary['orphaned_recharges_count'] ?? 0) > 0)
                <div class="mt-4 p-4 bg-red-50 border-l-4 border-red-500 rounded-r-lg dark:bg-red-900/10 dark:border-red-600">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <x-heroicon-o-exclamation-triangle class="h-5 w-5 text-red-500" />
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800 dark:text-red-200">
                                Discrepancia en Recargas
                            </h3>
                            <div class="mt-2 text-sm text-red-700 dark:text-red-300">
                                <p>
                                    Hay <strong>{{ $summary['orphaned_recharges_count'] }}</strong> recargas por valor de
                                    <strong>${{ number_format($summary['orphaned_recharges_amount'] ?? 0, 2, ',', '.') }}</strong>
                                    correspondientes a líneas que <strong>NO están presentes en el reporte de Pagos
                                        Claro</strong> de este mes.
                                </p>
                                <p class="mt-1">
                                    Estas recargas variables <strong class="uppercase">no serán liquidadas</strong> ya que
                                    no existe base del operador para calcular el residual.
                                </p>
                                <div class="mt-3 flex gap-3">
                                    <x-filament::button wire:click="exportOrphans" color="danger" size="xs"
                                        icon="heroicon-m-arrow-down-tray">
                                        Descargar Discrepancias
                                    </x-filament::button>

                                    {{ $this->deleteOrphansAction }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @endif

        @if($hasSelectedPeriod)
            <div
                class="rounded-2xl border border-slate-200/70 bg-white/90 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div
                    class="px-4 py-4 border-b border-slate-100/70 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between dark:border-slate-800">
                    <div class="space-y-1">
                        <h3 class="text-base font-semibold text-slate-900 dark:text-white">Registros importados</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Vista detallada de todos los campos
                            entregados
                            por Claro. Usa el scroll horizontal para navegar por las 86+ columnas.</p>
                        @if($hasRows)
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                {{ $fullRowsTotal }} registros totales
                            </p>
                        @endif
                    </div>
                    @if($hasRows)
                        <div class="flex flex-wrap items-center gap-3">
                            <label class="flex items-center gap-2 text-sm font-medium text-slate-600 dark:text-slate-300">
                                Mostrar
                                <select wire:model.live="fullRowsPerPage"
                                    class="fi-input rounded-xl border-slate-200 bg-white px-3 py-1 text-slate-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                                    @foreach($perPageOptions as $option)
                                        <option value="{{ $option }}">{{ $option }}</option>
                                    @endforeach
                                </select>
                                registros
                            </label>
                            <x-filament::button wire:click="export" icon="heroicon-o-arrow-down-tray" size="sm" color="primary">
                                Exportar CSV
                            </x-filament::button>
                        </div>
                    @endif
                </div>

                @if($hasRows)
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse border border-slate-300 dark:border-slate-700">
                            <thead>
                                <tr>
                                    @foreach($fullColumns as $column)
                                        <th
                                            class="border border-slate-300 dark:border-slate-700 px-3 py-2 bg-slate-100 dark:bg-slate-800 text-left text-xs font-semibold">
                                            {{ $column['label'] }}
                                        </th>
                                    @endforeach
                                    <th
                                        class="border border-slate-300 dark:border-slate-700 px-3 py-2 bg-slate-100 dark:bg-slate-800 text-left text-xs font-semibold">
                                        Total pagado (80+20)</th>
                                    <th
                                        class="border border-slate-300 dark:border-slate-700 px-3 py-2 bg-slate-100 dark:bg-slate-800 text-left text-xs font-semibold">
                                        Calculo (Monto*%)</th>
                                    <th
                                        class="border border-slate-300 dark:border-slate-700 px-3 py-2 bg-slate-100 dark:bg-slate-800 text-left text-xs font-semibold">
                                        Diferencia</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($fullRows as $row)
                                    <tr>
                                        @foreach($fullColumns as $column)
                                            <td class="border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs">
                                                {{ $row[$column['key']] ?? '' }}
                                            </td>
                                        @endforeach
                                        <td class="border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs font-semibold">
                                            ${{ number_format($row['total_pagado'] ?? 0, 2, ',', '.') }}</td>
                                        <td class="border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs">
                                            ${{ number_format($row['calc_monto_porcentaje'] ?? 0, 2, ',', '.') }}</td>
                                        @php
                                            $diff = $row['diferencia_pago'] ?? 0;
                                        @endphp
                                        <td class="border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs">
                                            ${{ number_format($diff, 2, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="border border-slate-300 dark:border-slate-700 px-4 py-8 text-center text-sm"
                                            colspan="{{ count($fullColumns) + 3 }}">
                                            No hay datos para mostrar en la tabla completa.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Pagination Controls --}}
                    <div class="flex items-center justify-between px-4 py-3 border-t border-slate-200 dark:border-slate-700">
                        <div class="flex flex-1 justify-between sm:hidden">
                            <x-filament::button wire:click="previousPage" :disabled="$this->page <= 1" size="sm" color="gray">
                                Anterior
                            </x-filament::button>
                            <x-filament::button wire:click="nextPage" :disabled="count($fullRows) < $currentPerPage" size="sm"
                                color="gray">
                                Siguiente
                            </x-filament::button>
                        </div>
                        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700 dark:text-gray-300">
                                    Página <span class="font-medium">{{ $this->page }}</span>
                                </p>
                            </div>
                            <div>
                                <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                                    <x-filament::button wire:click="previousPage" :disabled="$this->page <= 1" size="sm"
                                        color="gray" class="rounded-r-none">
                                        Anterior
                                    </x-filament::button>
                                    <x-filament::button wire:click="nextPage" :disabled="count($fullRows) < $currentPerPage"
                                        size="sm" color="gray" class="rounded-l-none">
                                        Siguiente
                                    </x-filament::button>
                                </nav>
                            </div>
                        </div>
                    </div>

                @else
                    <div class="px-4 py-6 text-center text-sm text-slate-500 dark:text-slate-400">
                        No se cargaron Pagos Claro para este periodo. Usa la importación de Pagos Claro para ver registros.
                    </div>
                @endif
            </div>

            <div
                class="rounded-2xl border border-primary-100/60 bg-white/90 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div
                    class="px-4 py-4 border-b border-slate-100/70 flex flex-col gap-4 md:flex-row md:items-center md:justify-between dark:border-slate-800">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900 dark:text-white">Recargas / Variables del
                            periodo
                        </h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Estos valores alimentan el cálculo
                            residual
                            del tendero.</p>
                    </div>

                    @if($rechargeRowsTotal > 0)
                        <div class="flex flex-wrap items-center gap-3">
                            <div class="text-sm text-slate-500 dark:text-slate-300 mr-2">
                                Total: <span
                                    class="font-semibold text-slate-900 dark:text-slate-100">{{ $rechargeRowsTotal }}</span>
                            </div>
                            <label class="flex items-center gap-2 text-sm font-medium text-slate-600 dark:text-slate-300">
                                Mostrar
                                <select wire:model.live="rechargeRowsPerPage"
                                    class="fi-input rounded-xl border-slate-200 bg-white px-3 py-1 text-slate-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                                    @foreach($perPageOptions as $option)
                                        <option value="{{ $option }}">{{ $option }}</option>
                                    @endforeach
                                </select>
                                registros
                            </label>
                            <x-filament::button wire:click="exportRecharges" icon="heroicon-o-arrow-down-tray" size="sm"
                                color="success">
                                Exportar CSV
                            </x-filament::button>
                        </div>
                    @endif
                </div>

                @if($rechargeRowsTotal > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse border border-slate-300 dark:border-slate-700">
                            <thead>
                                <tr class="bg-slate-100 dark:bg-slate-800 text-xs font-semibold">
                                    <th class="border border-slate-300 dark:border-slate-700 px-3 py-2 text-left">ICCID</th>
                                    <th class="border border-slate-300 dark:border-slate-700 px-3 py-2 text-left">Teléfono
                                    </th>
                                    <th class="border border-slate-300 dark:border-slate-700 px-3 py-2 text-left">Monto
                                        recarga
                                    </th>
                                    <th class="border border-slate-300 dark:border-slate-700 px-3 py-2 text-left">Periodo
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($displayRechargeRows as $row)
                                    <tr class="text-xs">
                                        <td class="border border-slate-300 dark:border-slate-700 px-3 py-2">
                                            {{ $row['iccid'] ?? 'N/D' }}
                                        </td>
                                        <td class="border border-slate-300 dark:border-slate-700 px-3 py-2">
                                            {{ $row['phone_number'] ?? 'N/D' }}
                                        </td>
                                        <td class="border border-slate-300 dark:border-slate-700 px-3 py-2 font-semibold">
                                            ${{ number_format($row['recharge_amount'] ?? 0, 2, ',', '.') }}</td>
                                        <td class="border border-slate-300 dark:border-slate-700 px-3 py-2">
                                            {{ $row['period_label'] ?? $row['period_date'] ?? 'N/D' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="px-4 py-6 text-center text-sm text-slate-500 dark:text-slate-400">
                        No se cargaron recargas para este periodo. Usa la importación de Recargas / Variables para
                        complementar
                        la consolidación.
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>