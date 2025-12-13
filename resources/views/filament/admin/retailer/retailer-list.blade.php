<x-filament-panels::page>
    <div class="min-h-[75vh] bg-gradient-to-b from-red-50/80 via-white to-white px-4 py-8 sm:px-6 lg:px-10">
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="space-y-2">
                    <a
                        href="{{ $this->getModuleUrl() }}"
                        class="inline-flex items-center gap-2 text-slate-700 transition hover:text-red-600"
                    >
                        <x-filament::icon icon="heroicon-s-arrow-left" class="w-5 h-5" />
                        Volver
                    </a>
                    <div>
                        <h1 class="text-2xl md:text-3xl font-semibold text-red-600">Tenderos</h1>
                        <p class="text-sm text-slate-600">
                            Gestiona tenderos vinculados a las tiendas. Puedes ver su estado y datos de contacto.
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <x-filament::button
                        tag="a"
                        color="primary"
                        icon="heroicon-o-user-plus"
                        href="{{ $this->getRetailerCreateUrl() }}"
                    >
                        Crear tendero
                    </x-filament::button>

                    <x-filament::button
                        color="gray"
                        icon="heroicon-o-arrow-down-tray"
                        wire:click="exportExcel"
                    >
                        Exportar Excel
                    </x-filament::button>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-2 shadow-sm">
                {{ $this->table }}
            </div>
        </div>
    </div>
</x-filament-panels::page>
