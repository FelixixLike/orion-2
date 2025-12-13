<x-filament-panels::page>
    <div class="min-h-[75vh] bg-gradient-to-b from-red-50/80 via-white to-white px-4 py-8 sm:px-6 lg:px-10">
        <div class="mx-auto max-w-6xl space-y-8">
            <div>
                <a
                    href="{{ $this->getModuleUrl() }}"
                    class="mb-4 inline-flex items-center gap-2 text-slate-700 transition hover:text-red-600"
                >
                    <x-filament::icon icon="heroicon-s-arrow-left" class="h-5 w-5" />
                    Volver
                </a>

                <div>
                    <h1 class="text-2xl font-semibold text-red-600">Crear usuario</h1>
                    <p class="text-slate-600">Crea usuarios administrativos uno a uno (tenderos se crean en su módulo).</p>
                </div>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <div
                    class="flex cursor-pointer flex-col justify-between rounded-3xl border-2 border-transparent bg-white p-8 shadow-md transition hover:-translate-y-1 hover:border-red-500/70 hover:shadow-xl"
                >
                    <div class="space-y-4 text-center">
                        <div class="mb-2 flex h-20 w-20 items-center justify-center rounded-full bg-red-100 text-red-600">
                            <x-filament::icon icon="heroicon-o-user-plus" class="h-10 w-10" />
                        </div>

                        <div class="space-y-1">
                            <h2 class="text-2xl font-semibold text-slate-900">Crear uno</h2>
                            <p class="text-sm text-slate-600">Registra un usuario administrativo</p>
                        </div>

                        <p class="text-sm text-slate-600">
                            El formulario incluye roles, permisos y la opción de forzar cambio de contraseña al primer acceso.
                            No se permite carga masiva en este módulo.
                        </p>
                    </div>

                    <div class="mt-6 text-center">
                        <a
                            href="{{ $this->getUserFormUrl() }}"
                            class="inline-flex items-center gap-2 font-semibold text-red-600 hover:text-red-700"
                        >
                            Abrir formulario
                            <x-filament::icon icon="heroicon-s-arrow-right" class="h-4 w-4" />
                        </a>
                    </div>
                </div>

                <div class="flex flex-col gap-5 rounded-3xl border-2 border-slate-100 bg-white p-8 shadow-md">
                    <div class="space-y-4 text-center">
                        <div class="mb-2 flex h-20 w-20 items-center justify-center rounded-full bg-blue-100 text-blue-600">
                            <x-filament::icon icon="heroicon-o-key" class="h-10 w-10" />
                        </div>

                        <div class="space-y-1">
                            <h2 class="text-2xl font-semibold text-slate-900">Buenas prácticas</h2>
                            <p class="text-sm text-slate-600">Activa “Forzar cambio de contraseña” para accesos seguros.</p>
                        </div>

                        <p class="text-sm text-slate-600">
                            Si el usuario es suspendido o cambia de rol, puedes editarlo desde el listado. El módulo excluye tenderos.
                        </p>
                    </div>

                    <div class="flex flex-col gap-2">
                        <x-filament::button
                            tag="a"
                            color="primary"
                            class="w-full justify-center gap-2"
                            href="{{ $this->getUserListUrl() }}"
                            icon="heroicon-o-users"
                        >
                            Ver usuarios
                        </x-filament::button>
                        <x-filament::button
                            tag="a"
                            color="gray"
                            class="w-full justify-center gap-2"
                            href="{{ $this->getModuleUrl() }}"
                            icon="heroicon-o-squares-2x2"
                        >
                            Volver al módulo
                        </x-filament::button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
