<x-filament-panels::page>
    <div class="container mx-auto px-4 py-12">
        <div class="mx-auto max-w-5xl space-y-10">
            <div class="text-center space-y-3">
                <div
                    class="mx-auto mb-3 inline-flex h-16 w-16 items-center justify-center rounded-full bg-primary-600 shadow-lg shadow-primary-200">
                    <x-filament::icon icon="heroicon-o-user-group" class="h-8 w-8 text-white" />
                </div>
                <h1 class="text-3xl font-semibold text-primary-700">Modulo de Tenderos</h1>
                <p class="text-slate-600">Para equipos TAT: crea, edita y sigue tenderos vinculados a las tiendas.</p>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <a href="{{ $this->getRetailerListUrl() }}"
                    class="block cursor-pointer rounded-3xl border-2 border-transparent bg-white shadow-md transition hover:scale-[1.01] hover:border-primary-600 hover:shadow-xl">
                    <div class="p-8">
                        <div class="space-y-4 pb-4 text-center">
                            <div
                                class="mx-auto mb-2 flex h-20 w-20 items-center justify-center rounded-full bg-primary-100">
                                <x-filament::icon icon="heroicon-o-list-bullet" class="h-10 w-10 text-primary-600" />
                            </div>
                            <h2 class="text-2xl font-semibold text-slate-900">Listado de tenderos</h2>
                            <p class="text-sm text-slate-600">Busca, filtra y entra a editar cada tendero.</p>
                        </div>

                        <p class="mb-4 text-center text-sm text-slate-600">
                            Accede al listado con columnas de estado, rol y correo. Desde ahi puedes abrir edicion o
                            revisar el estado de activacion.
                        </p>

                        <div class="flex items-center justify-center gap-2 font-semibold text-primary-600">
                            <span>Ir al listado</span>
                            <x-filament::icon icon="heroicon-o-arrow-right" class="h-4 w-4" />
                        </div>
                    </div>
                </a>

                <a href="{{ $this->getRetailerCreateUrl() }}"
                    class="block cursor-pointer rounded-3xl border-2 border-transparent bg-white shadow-md transition hover:scale-[1.01] hover:border-primary-600 hover:shadow-xl">
                    <div class="p-8">
                        <div class="space-y-4 pb-4 text-center">
                            <div
                                class="mx-auto mb-2 flex h-20 w-20 items-center justify-center rounded-full bg-primary-100">
                                <x-filament::icon icon="heroicon-o-user-plus" class="h-10 w-10 text-primary-600" />
                            </div>
                            <h2 class="text-2xl font-semibold text-slate-900">Crear tendero</h2>
                            <p class="text-sm text-slate-600">Registra un tendero con rol, estado y tiendas asignadas.
                            </p>
                        </div>

                        <p class="mb-4 text-center text-sm text-slate-600">
                            El formulario incluye asignacion de tiendas y credenciales. Los usuarios TAT solo veran rol
                            Tendero.
                        </p>

                        <div class="flex items-center justify-center gap-2 font-semibold text-primary-600">
                            <span>Crear tendero</span>
                            <x-filament::icon icon="heroicon-o-arrow-right" class="h-4 w-4" />
                        </div>
                    </div>
                </a>
            </div>


        </div>
    </div>
</x-filament-panels::page>