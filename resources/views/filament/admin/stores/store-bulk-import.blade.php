<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-3xl border border-slate-200/80 bg-gradient-to-r from-white via-slate-50 to-slate-100 p-6 shadow-lg shadow-slate-200/70 dark:border-slate-800 dark:from-slate-950 dark:via-slate-900 dark:to-slate-950 dark:shadow-black/40">
            <div class="space-y-2">
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-primary-600 dark:text-primary-300">Importacion</p>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Cargar varias tiendas</h1>
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    Sube el archivo XLSX con el formato de la plantilla. Cada fila crea o vincula un tendero y su tienda.
                </p>
                <div>
                    <a href="{{ $this->getTemplateUrl() }}" class="text-primary-700 underline-offset-4 hover:underline dark:text-primary-300" target="_blank">
                        Descargar plantilla Tiendas.xlsx
                    </a>
                </div>
            </div>
        </div>

        <x-filament::card>
            {{ $this->form }}
            <div class="mt-4 flex items-center justify-end">
                <x-filament::button color="primary" wire:click="submit" icon="heroicon-o-arrow-up-tray">
                    Importar
                </x-filament::button>
            </div>
        </x-filament::card>

        @if (!empty($summary))
            <x-filament::card>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Filas procesadas</p>
                        <p class="text-2xl font-semibold text-slate-900 dark:text-white">{{ $summary['procesadas'] ?? 0 }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Tenderos creados</p>
                        <p class="text-2xl font-semibold text-slate-900 dark:text-white">{{ $summary['tenderos_creados'] ?? 0 }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Tiendas creadas</p>
                        <p class="text-2xl font-semibold text-slate-900 dark:text-white">{{ $summary['tiendas_creadas'] ?? 0 }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Tiendas saltadas</p>
                        <p class="text-2xl font-semibold text-slate-900 dark:text-white">{{ $summary['tiendas_saltadas'] ?? 0 }}</p>
                    </div>
                </div>
            </x-filament::card>
        @endif
    </div>
</x-filament-panels::page>
