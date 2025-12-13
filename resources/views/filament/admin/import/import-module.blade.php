<x-filament-panels::page>
    <div class="min-h-[70vh] bg-gradient-to-b from-primary-50/80 via-white to-white px-4 py-10 sm:px-6 lg:px-10">
        <div class="mx-auto max-w-6xl space-y-8">
            <div class="space-y-3">
                <div class="inline-flex items-center gap-2 rounded-full bg-primary-100 px-3 py-1 text-xs font-semibold text-primary-700">
                    <x-filament::icon icon="heroicon-o-arrow-up-tray" class="h-4 w-4" />
                    Importación de datos
                </div>
                <h1 class="text-2xl md:text-3xl font-semibold text-primary-700">Módulo de Importación</h1>
                <p class="max-w-3xl text-sm text-slate-600">
                    Carga archivos y revisa su estado: recibidos, procesando o completados. Las importaciones se ejecutan en segundo plano, puedes revisar el historial y el detalle de cada carga.
                </p>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <a href="{{ $this->getImportCreateUrl() }}" class="block cursor-pointer rounded-3xl border-2 border-transparent bg-white shadow-md transition hover:scale-[1.01] hover:border-primary-600 hover:shadow-xl">
                    <div class="p-8 space-y-4">
                        <div class="flex items-center gap-3">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary-100 text-primary-700">
                                <x-filament::icon icon="heroicon-o-cloud-arrow-up" class="h-6 w-6" />
                            </span>
                            <div>
                                <h2 class="text-xl font-semibold text-slate-900">Nueva importación</h2>
                                <p class="text-sm text-slate-600">Sube un archivo y monitorea su avance en tiempo real.</p>
                            </div>
                        </div>
                        <p class="text-sm text-slate-500">
                            Acepta archivos XLSX y muestra estados: pendiente, procesando, completado o fallido. Recibirás notificaciones al finalizar.
                        </p>
                        <div class="flex items-center gap-2 text-sm font-semibold text-primary-700">
                            <span>Iniciar importación</span>
                            <x-filament::icon icon="heroicon-o-arrow-right" class="h-4 w-4" />
                        </div>
                    </div>
                </a>

                <a href="{{ $this->getImportListUrl() }}" class="block cursor-pointer rounded-3xl border-2 border-transparent bg-white shadow-md transition hover:scale-[1.01] hover:border-primary-600 hover:shadow-xl">
                    <div class="p-8 space-y-4">
                        <div class="flex items-center gap-3">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary-100 text-primary-700">
                                <x-filament::icon icon="heroicon-o-queue-list" class="h-6 w-6" />
                            </span>
                            <div>
                                <h2 class="text-xl font-semibold text-slate-900">Historial y estados</h2>
                                <p class="text-sm text-slate-600">Consulta cada carga, su resultado y quién la ejecutó.</p>
                            </div>
                        </div>
                        <p class="text-sm text-slate-500">
                            Filtra por estado para encontrar importaciones pendientes o revisar errores. Ingresa al detalle para ver filas procesadas y mensajes.
                        </p>
                        <div class="flex items-center gap-2 text-sm font-semibold text-primary-700">
                            <span>Ver importaciones</span>
                            <x-filament::icon icon="heroicon-o-arrow-right" class="h-4 w-4" />
                        </div>
                    </div>
                </a>
            </div>

            <div class="rounded-3xl border border-primary-100 bg-white/70 p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">Cómo funciona</h3>
                <ul class="mt-3 space-y-2 text-sm text-slate-600">
                    <li class="flex items-start gap-2">
                        <span class="mt-1 h-2 w-2 rounded-full bg-primary-600"></span>
                        Sube el archivo y asigna un nombre descriptivo para rastrear el proceso.
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="mt-1 h-2 w-2 rounded-full bg-primary-600"></span>
                        Sigue el estado en la lista: pendiente, procesando, completado o fallido.
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="mt-1 h-2 w-2 rounded-full bg-primary-600"></span>
                        Abre el detalle de una importación para ver mensajes y filas afectadas.
                    </li>
                </ul>
            </div>
        </div>
    </div>
</x-filament-panels::page>
