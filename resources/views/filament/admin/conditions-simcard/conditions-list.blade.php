<x-filament-panels::page>
    <div class="min-h-[75vh] bg-gradient-to-b from-red-50/80 via-white to-white px-4 py-8 sm:px-6 lg:px-10">
        <div class="mx-auto max-w-7xl space-y-8">
            {{-- HEADER --}}
            <div class="space-y-4">
                <a href="{{ $this->getModuleUrl() }}"
                    class="inline-flex items-center gap-2 text-slate-700 hover:text-red-600 transition">
                    <x-filament::icon icon="heroicon-s-arrow-left" class="w-5 h-5" />
                    Volver
                </a>

                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 class="text-2xl md:text-3xl font-semibold text-red-600">Condiciones de SIM</h1>
                        <p class="text-sm text-slate-600">
                            Ver y gestionar las condiciones de comisión aplicadas a cada SIM (ICCID).
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <x-filament::button tag="a" color="primary" icon="heroicon-o-plus"
                            href="{{ $this->getCreateUrl() }}">
                            Crear condición
                        </x-filament::button>

                        <x-filament::button wire:click="exportToExcel" color="success"
                            icon="heroicon-o-arrow-down-tray">
                            Exportar a CSV
                        </x-filament::button>
                    </div>
                </div>
            </div>

            {{-- TABLA FILAMENT --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-2 shadow-sm">
                {{ $this->table }}
            </div>
        </div>
    </div>
</x-filament-panels::page>