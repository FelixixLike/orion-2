<x-filament-panels::page>
    <div class="container mx-auto px-4 py-12">
        <div class="mx-auto max-w-5xl space-y-10">
            <div class="text-center space-y-3">
                <div class="mx-auto mb-3 inline-flex h-16 w-16 items-center justify-center rounded-full bg-primary-600 shadow-lg shadow-primary-200">
                    <x-filament::icon icon="heroicon-o-users" class="h-8 w-8 text-white" />
                </div>
                <h1 class="text-3xl font-semibold text-primary-700">Módulo de Usuarios</h1>
                <p class="text-slate-600">Gestiona usuarios del sistema (excluye tenderos). Crea, edita o suspende según permisos.</p>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <a href="{{ $this->getUserListUrl() }}" class="block cursor-pointer rounded-3xl border-2 border-transparent bg-white shadow-md transition hover:scale-[1.01] hover:border-primary-600 hover:shadow-xl">
                    <div class="p-8">
                        <div class="space-y-4 pb-4 text-center">
                            <div class="mx-auto mb-2 flex h-20 w-20 items-center justify-center rounded-full bg-primary-100">
                                <x-filament::icon icon="heroicon-o-list-bullet" class="h-10 w-10 text-primary-600" />
                            </div>
                            <h2 class="text-2xl font-semibold text-slate-900">Lista de usuarios</h2>
                            <p class="text-sm text-slate-600">Ver y gestionar usuarios administrativos.</p>
                        </div>

                        <p class="mb-4 text-center text-sm text-slate-600">
                            Tabla filtrada para roles distintos a Tendero. Accede a edición, estados y permisos según tu rol.
                        </p>

                        <div class="flex items-center justify-center gap-2 font-semibold text-primary-600">
                            <span>Ir al listado</span>
                            <x-filament::icon icon="heroicon-o-arrow-right" class="h-4 w-4" />
                        </div>
                    </div>
                </a>

                <a href="{{ $this->getUserCreateUrl() }}" class="block cursor-pointer rounded-3xl border-2 border-transparent bg-white shadow-md transition hover:scale-[1.01] hover:border-primary-600 hover:shadow-xl">
                    <div class="p-8">
                        <div class="space-y-4 pb-4 text-center">
                            <div class="mx-auto mb-2 flex h-20 w-20 items-center justify-center rounded-full bg-primary-100">
                                <x-filament::icon icon="heroicon-o-user-plus" class="h-10 w-10 text-primary-600" />
                            </div>
                            <h2 class="text-2xl font-semibold text-slate-900">Crear usuario</h2>
                            <p class="text-sm text-slate-600">Crea un usuario a la vez con credenciales seguras.</p>
                        </div>

                        <p class="mb-4 text-center text-sm text-slate-600">
                            Incluye opción de forzar cambio de contraseña en primer login. Solo para roles administrativos.
                        </p>

                        <div class="flex items-center justify-center gap-2 font-semibold text-primary-600">
                            <span>Abrir formulario</span>
                            <x-filament::icon icon="heroicon-o-arrow-right" class="h-4 w-4" />
                        </div>
                    </div>
                </a>
            </div>

            <div class="rounded-3xl border-2 border-dashed border-primary-200 bg-primary-50/60 p-6 shadow-inner">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div class="flex items-start gap-3">
                        <div class="mt-1 flex h-10 w-10 items-center justify-center rounded-full bg-white text-primary-600 shadow-sm">
                            <x-filament::icon icon="heroicon-o-key" class="h-6 w-6" />
                        </div>
                        <div class="space-y-1">
                            <p class="text-lg font-semibold text-primary-800">Seguridad y accesos</p>
                            <p class="text-sm text-primary-700">
                                Usa “Forzar cambio de contraseña” para obligar al usuario a renovarla al ingresar.
                            </p>
                            <p class="text-sm text-primary-700">
                                El listado excluye tenderos; ellos se gestionan en el módulo de Tenderos.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
