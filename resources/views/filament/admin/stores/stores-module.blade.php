<x-filament-panels::page>
    <div class="container mx-auto px-4 py-12">
        <div class="mx-auto max-w-5xl space-y-10">
            <div class="text-center">
                <div class="mx-auto mb-4 inline-flex h-16 w-16 items-center justify-center rounded-full bg-primary-600 shadow-lg shadow-primary-200">
                    <x-filament::icon icon="heroicon-o-building-storefront" class="h-8 w-8 text-white" />
                </div>
                <h1 class="mb-2 text-lg font-semibold text-primary-600">Modulo de Tiendas</h1>
                <p class="text-slate-600">Gestiona tus tiendas de forma facil y rapida</p>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <a
                    href="{{ $this->getStoreListUrl() }}"
                    wire:navigate
                    class="block cursor-pointer rounded-3xl border-2 border-transparent bg-white shadow-md transition hover:scale-[1.01] hover:border-primary-600 hover:shadow-xl"
                >
                    <div class="p-8">
                        <div class="space-y-4 pb-4 text-center">
                            <div class="mx-auto mb-2 flex h-20 w-20 items-center justify-center rounded-full bg-primary-100">
                                <x-filament::icon icon="heroicon-o-list-bullet" class="h-10 w-10 text-primary-600" />
                            </div>
                            <h2 class="text-2xl font-semibold text-slate-900">Lista de Tiendas</h2>
                            <p class="text-sm text-slate-600">Ver y gestionar todas las tiendas existentes</p>
                        </div>

                        <p class="mb-4 text-center text-sm text-slate-600">
                            Accede a la lista completa de tiendas, edita informacion, consulta detalles y administra tu red de locales.
                        </p>

                        <div class="flex items-center justify-center gap-2 font-semibold text-primary-600">
                            <span>Ir a la lista</span>
                            <x-filament::icon icon="heroicon-o-arrow-right" class="h-4 w-4" />
                        </div>
                    </div>
                </a>

                <a
                    href="{{ $this->getStoreCreateUrl() }}"
                    wire:navigate
                    class="block cursor-pointer rounded-3xl border-2 border-transparent bg-white shadow-md transition hover:scale-[1.01] hover:border-primary-600 hover:shadow-xl"
                >
                    <div class="p-8">
                        <div class="space-y-4 pb-4 text-center">
                            <div class="mx-auto mb-2 flex h-20 w-20 items-center justify-center rounded-full bg-primary-100">
                                <x-filament::icon icon="heroicon-o-plus" class="h-10 w-10 text-primary-600" />
                            </div>
                            <h2 class="text-2xl font-semibold text-slate-900">Crear Tienda</h2>
                            <p class="text-sm text-slate-600">Anade una nueva tienda al sistema</p>
                        </div>

                        <p class="mb-4 text-center text-sm text-slate-600">
                            Registra una nueva tienda en el sistema con toda la informacion necesaria: nombre, ubicacion, horarios y mas.
                        </p>

                        <div class="flex items-center justify-center gap-2 font-semibold text-primary-600">
                            <span>Crear nueva tienda</span>
                            <x-filament::icon icon="heroicon-o-arrow-right" class="h-4 w-4" />
                        </div>
                    </div>
                </a>

            </div>

            <div class="mt-2 text-center text-sm text-slate-500">
                <p>Selecciona una opcion para comenzar</p>
            </div>
        </div>
    </div>
</x-filament-panels::page>
