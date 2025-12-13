<x-filament-panels::page>
    <div class="min-h-[75vh] bg-gradient-to-b from-red-50/80 via-white to-white px-4 py-8 sm:px-6 lg:px-10">
        <div class="mx-auto max-w-6xl space-y-8">
            <div>
                <a href="{{ $this->getModuleUrl() }}"
                    class="mb-4 inline-flex items-center gap-2 text-slate-700 transition hover:text-red-600">
                    <x-filament::icon icon="heroicon-s-arrow-left" class="h-5 w-5" />
                    Volver
                </a>

                <div>
                    <h1 class="text-2xl font-semibold text-red-600">Crear tendero</h1>
                    <p class="text-slate-600">
                        Registra un tendero con sus datos básicos y tiendas asignadas. El sistema enviará un correo de
                        activación.
                    </p>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                {{ $this->form }}

                <div class="mt-6 flex justify-end">
                    <x-filament::button wire:click="create" icon="heroicon-o-check-circle">
                        Crear tendero
                    </x-filament::button>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <x-filament::button tag="a" color="gray" class="justify-center gap-2"
                    href="{{ $this->getRetailerListUrl() }}" icon="heroicon-o-users">
                    Ver tenderos
                </x-filament::button>
                <x-filament::button tag="a" color="primary" class="justify-center gap-2"
                    href="{{ $this->getModuleUrl() }}" icon="heroicon-o-squares-2x2">
                    Volver al módulo
                </x-filament::button>
            </div>
        </div>
    </div>
</x-filament-panels::page>