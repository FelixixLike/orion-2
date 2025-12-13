@php
    use Illuminate\Support\Number;
    $hasSummaries = count($summaries) > 0;
@endphp

<x-filament-panels::page>
    <div class="space-y-4">
        <div class="space-y-1">
            <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                Cruce de pagos Claro vs Movilco vs tenderos
            </h1>
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Revisa diferencias entre lo que Claro pagó, lo que Movilco registra y lo trasladado al tendero.
            </p>
        </div>

        <x-filament::card>
            {{ $this->form }}
        </x-filament::card>

        @if (!$hasSummaries)
            <x-filament::card>
                <p class="text-sm text-gray-600 dark:text-gray-300">No hay datos para el periodo seleccionado.</p>
            </x-filament::card>
        @endif

        @if ($hasSummaries)
            <x-filament::card class="relative">
                <div wire:loading.flex wire:target="loadPreview"
                    class="absolute inset-0 z-10 bg-white/50 dark:bg-gray-900/50 backdrop-blur-sm flex items-center justify-center">
                    <x-filament::loading-indicator class="w-10 h-10 text-primary-600" />
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold text-gray-600 uppercase">Periodo</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-600 uppercase">Líneas Claro</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-600 uppercase">Comisión Claro</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-600 uppercase">Recargas Movilco</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-600 uppercase">Total Pagado</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-600 uppercase">Utilidad</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-600 uppercase">Estado liquidación
                                </th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-600 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                            @foreach ($summaries as $summary)
                                @php
                                    $diff = $summary['difference'];
                                    $alert = $summary['alert'] ?? false;
                                    $statusColor = match ($summary['status']) {
                                        'Completadas', 'Liquidada' => 'success',
                                        'Pendiente' => 'warning',
                                        'Parcial' => 'info',
                                        default => 'gray',
                                    };
                                @endphp
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/30">
                                    <td class="px-3 py-2 font-semibold text-gray-800 dark:text-gray-100">
                                        {{ $summary['period'] }}
                                    </td>
                                    <td class="px-3 py-2">{{ $summary['total_lines'] }}</td>
                                    <td class="px-3 py-2">{{ Number::currency($summary['total_commission_claro'], 'COP') }}</td>
                                    <td class="px-3 py-2">{{ Number::currency($summary['total_recargas'], 'COP') }}</td>
                                    <td class="px-3 py-2 font-bold">{{ Number::currency($summary['total_pagado'] ?? 0, 'COP') }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <span class="{{ $diff >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                                            {{ Number::currency($diff, 'COP') }}
                                        </span>
                                        @if ($alert)
                                            <x-filament::badge color="danger" class="ml-2">Diferencia alta</x-filament::badge>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">
                                        <x-filament::badge color="{{ $statusColor }}">
                                            {{ $summary['status'] }}
                                        </x-filament::badge>
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <div class="flex flex-col gap-2 items-center">
                                            @if (in_array($summary['status'], ['Completadas', 'Liquidada']))
                                                <x-filament::button size="xs" color="gray" icon="heroicon-o-eye"
                                                    wire:click="loadPreview('{{ $summary['period'] }}')">
                                                    Ver Detalle
                                                </x-filament::button>
                                            @else
                                                <x-filament::button size="xs" color="primary" icon="heroicon-o-currency-dollar"
                                                    wire:click="loadPreview('{{ $summary['period'] }}')">
                                                    Liquidar
                                                </x-filament::button>
                                            @endif

                                            <div class="flex gap-2">
                                                @if ($summary['operator_import_id'] && !in_array($summary['status'], ['Completadas', 'Liquidada']))
                                                    <x-filament::button size="xs" color="danger" outlined icon="heroicon-o-trash"
                                                        tooltip="Borrar TOODOS Pagos Claro del periodo"
                                                        wire:click="promptDeleteImportsForPeriod('{{ $summary['period'] }}', 'operator_report', 'PAGOS CLARO')">
                                                        Claro
                                                    </x-filament::button>
                                                @endif

                                                @if ($summary['recharge_import_id'] && !in_array($summary['status'], ['Completadas', 'Liquidada']))
                                                    <x-filament::button size="xs" color="warning" outlined icon="heroicon-o-trash"
                                                        tooltip="Borrar TODAS las Recargas del periodo"
                                                        wire:click="promptDeleteImportsForPeriod('{{ $summary['period'] }}', 'recharge', 'RECARGAS')">
                                                        Rec
                                                    </x-filament::button>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::card>

            {{-- MODAL DE LIQUIDACIÓN --}}
            <x-filament::modal id="liquidation-preview-modal" :close-by-clicking-away="false" width="5xl"
                alignment="center">

                <x-slot name="heading">
                    Liquidación TAT {{ $previewPeriod }}
                </x-slot>

                <x-slot name="description">
                    Revisa los valores a transferir. Esta acción afectará el saldo de todas las tiendas listadas.
                </x-slot>

                {{-- Overlay de carga mientras se liquidan tiendas --}}
                <div wire:loading.flex wire:target="liquidateSelected"
                    class="absolute inset-0 z-20 bg-white/70 dark:bg-gray-900/70 backdrop-blur-sm flex items-center justify-center">
                    <x-filament::loading-indicator class="w-12 h-12 text-primary-600" />
                </div>

                {{-- Contenido de la Tabla de Previsualización --}}
                <div class="mt-4">
                    @include('filament.admin.crosses.partials.preview-table')
                </div>

                {{-- Acciones del pie del modal --}}
                <x-slot name="footer">
                    <div class="flex justify-between w-full">
                        <x-filament::button color="gray" wire:click="closePreview">
                            {{ $this->hasPendingLiquidations ? 'Cancelar' : 'Cerrar' }}
                        </x-filament::button>

                        @if($this->hasPendingLiquidations)
                            <x-filament::button color="danger"
                                wire:click="$dispatch('open-modal', {id: 'confirm-liquidation-modal'})">
                                CONFIRMAR Y LIQUIDAR TODO
                            </x-filament::button>
                        @endif
                    </div>
                </x-slot>
            </x-filament::modal>

            @if(!empty($previewPeriod) && count($previewStores) === 0)
                <x-filament::card>
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-semibold">No hay datos para {{ $previewPeriod }}</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Carga Pagos Claro y Recargas para poder generar la vista previa.
                            </p>
                        </div>
                        <x-filament::button color="gray" wire:click="closePreview">Cerrar</x-filament::button>
                    </div>
                </x-filament::card>
            @endif
        @endif
        {{-- MODAL DE ELIMINACIÓN --}}
        <x-filament::modal id="delete-import-modal" alignment="center" width="md">
            <x-slot name="heading">
                Confirmar eliminación
            </x-slot>
            <div class="py-4">
                <p class="text-gray-600 dark:text-gray-400">
                    ¿Estás seguro de ELIMINAR el archivo de <strong>{{ $importTypeLabelToDelete }}</strong>?
                </p>
                <p class="text-sm text-red-500 mt-2 font-medium">
                    Esta acción eliminará todos los registros asociados y NO se puede deshacer.
                </p>
            </div>
            <x-slot name="footer">
                <div class="flex justify-end gap-3 w-full">
                    <x-filament::button color="gray" wire:click="$dispatch('close-modal', {id: 'delete-import-modal'})">
                        Cancelar
                    </x-filament::button>
                    <x-filament::button color="danger" wire:click="confirmDeleteImport">
                        Sí, Eliminar
                    </x-filament::button>
                </div>
            </x-slot>
        </x-filament::modal>

        {{-- MODAL DE CONFIRMACIÓN DE LIQUIDACIÓN MASIVA --}}
        <x-filament::modal id="confirm-liquidation-modal" alignment="center" width="md">
            <x-slot name="heading">
                <div class="flex items-center gap-2 text-danger-600">
                    <x-heroicon-m-exclamation-triangle class="h-6 w-6" />
                    <span>Confirmar Liquidación Masiva</span>
                </div>
            </x-slot>

            <div class="py-4 space-y-3">
                <p class="font-bold text-lg text-gray-800 dark:text-gray-100 text-center">
                    ¿Estás seguro de liquidar {{ $previewPeriod }}?
                </p>

                <div
                    class="bg-red-50 dark:bg-red-900/20 p-4 rounded-lg border border-red-100 dark:border-red-800 text-sm text-red-700 dark:text-red-400">
                    <ul class="list-disc pl-5 space-y-1">
                        <li>Se crearán transferencias de saldo para <strong>TODAS</strong> las tiendas listadas
                            ({{ $previewTotalPages > 1 ? 'incluyendo las no visibles' : 'visibles' }}).</li>
                        <li>El dinero se acreditará inmediatamente en las billeteras.</li>
                        <li><strong>Esta acción es irreversible.</strong></li>
                    </ul>
                </div>

                <p class="text-sm text-center text-gray-600 dark:text-gray-400">
                    Por favor verifica que el "Total a Liquidar" coincida con tu reporte.
                </p>
            </div>

            <x-slot name="footer">
                <div class="flex justify-end gap-3 w-full">
                    <x-filament::button color="gray"
                        wire:click="$dispatch('close-modal', {id: 'confirm-liquidation-modal'})">
                        Cancelar
                    </x-filament::button>

                    <x-filament::button color="danger"
                        wire:click="liquidateAll; $dispatch('close-modal', {id: 'confirm-liquidation-modal'})">
                        SÍ, LIQUIDAR TODO
                    </x-filament::button>
                </div>
            </x-slot>
        </x-filament::modal>

    </div>
</x-filament-panels::page>