<x-filament-panels::page>
    <div class="container mx-auto px-4 py-12">
        <div class="mx-auto max-w-6xl space-y-10">
            <div class="text-center space-y-3">
                <div class="mx-auto mb-3 inline-flex h-16 w-16 items-center justify-center rounded-full bg-primary-600 shadow-lg shadow-primary-200">
                    <x-filament::icon icon="heroicon-o-credit-card" class="h-8 w-8 text-white" />
                </div>
                <h1 class="text-3xl font-semibold text-primary-700">Módulo de Condiciones SIM</h1>
                <p class="text-slate-600">Registra ventas de SIM: ICCID, teléfono, punto de venta, valor y comisión. Solo super_admin.</p>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <a href="{{ $this->getListUrl() }}" class="block cursor-pointer rounded-3xl border-2 border-transparent bg-white shadow-md transition hover:scale-[1.01] hover:border-primary-600 hover:shadow-xl">
                    <div class="p-8">
                        <div class="space-y-4 pb-4 text-center">
                            <div class="mx-auto mb-2 flex h-20 w-20 items-center justify-center rounded-full bg-primary-100">
                                <x-filament::icon icon="heroicon-o-list-bullet" class="h-10 w-10 text-primary-600" />
                            </div>
                            <h2 class="text-2xl font-semibold text-slate-900">Lista de condiciones</h2>
                            <p class="text-sm text-slate-600">Consulta ICCID, teléfono, valor, comisión y fecha de venta.</p>
                        </div>
                        <div class="flex items-center justify-center gap-2 font-semibold text-primary-600">
                            <span>Ir al listado</span>
                            <x-filament::icon icon="heroicon-o-arrow-right" class="h-4 w-4" />
                        </div>
                    </div>
                </a>

                <a href="{{ $this->getCreateUrl() }}" class="block cursor-pointer rounded-3xl border-2 border-transparent bg-white shadow-md transition hover:scale-[1.01] hover:border-primary-600 hover:shadow-xl">
                    <div class="p-8">
                        <div class="space-y-4 pb-4 text-center">
                            <div class="mx-auto mb-2 flex h-20 w-20 items-center justify-center rounded-full bg-primary-100">
                                <x-filament::icon icon="heroicon-o-plus" class="h-10 w-10 text-primary-600" />
                            </div>
                            <h2 class="text-2xl font-semibold text-slate-900">Crear manual</h2>
                            <p class="text-sm text-slate-600">Registra una SIM con ICCID único y datos de venta.</p>
                        </div>
                        <div class="flex items-center justify-center gap-2 font-semibold text-primary-600">
                            <span>Crear condición</span>
                            <x-filament::icon icon="heroicon-o-arrow-right" class="h-4 w-4" />
                        </div>
                    </div>
                </a>



                </a>
            </div>


        </div>
    </div>
</x-filament-panels::page>
