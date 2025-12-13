<x-filament-panels::page>
    <div
        class="min-h-[75vh] bg-gradient-to-b from-red-50/80 via-white to-white px-4 py-8 dark:bg-none dark:bg-[--admin-surface-muted] sm:px-6 lg:px-10">
        <div class="mx-auto max-w-6xl space-y-8">
            {{-- HEADER --}}
            <div>
                <a href="{{ $this->getModuleUrl() }}"
                    class="mb-4 inline-flex items-center gap-2 text-slate-700 transition hover:text-red-600 dark:text-[--admin-text-muted] dark:hover:text-[--admin-primary]">
                    <x-filament::icon icon="heroicon-s-arrow-left" class="h-5 w-5" />
                    Volver
                </a>

                <div>
                    <h1 class="text-2xl font-semibold text-red-600 dark:text-[--admin-primary]">Crear condición de SIM
                    </h1>
                    <p class="text-slate-600 dark:text-[--admin-text-muted]">
                        Registra manualmente una venta con ICCID único, valor y comisión.
                    </p>
                </div>
            </div>

            {{-- GRID PRINCIPAL --}}
            <div class="grid gap-6 md:grid-cols-2">
                {{-- CARD: CREAR UNA CONDICIÓN --}}
                <div
                    class="flex flex-col justify-between rounded-3xl border-2 border-transparent bg-white p-8 shadow-md transition hover:-translate-y-1 hover:border-red-500/70 hover:shadow-xl dark:bg-[--admin-surface] dark:hover:border-[--admin-primary]/50">
                    <div class="space-y-4">
                        <div
                            class="mb-2 flex h-20 w-20 items-center justify-center rounded-full bg-red-100 text-red-600 dark:bg-[--admin-primary-soft] dark:text-[--admin-primary]">
                            <x-filament::icon icon="heroicon-o-sparkles" class="h-10 w-10" />
                        </div>

                        <div class="space-y-1">
                            <h2 class="text-2xl font-semibold text-slate-900 dark:text-white">Registrar una condición
                            </h2>
                            <p class="text-sm text-slate-600 dark:text-[--admin-text-muted]">
                                Crea una condición individual para una SIM específica.
                            </p>
                        </div>

                        <p class="text-sm text-slate-600 dark:text-[--admin-text-muted]">
                            Completa el formulario con el ICCID, número, valor y comisión que aplican a esta venta.
                        </p>
                    </div>

                    <div class="mt-6">
                        {{-- FORMULARIO FILAMENT --}}
                        <div
                            class="rounded-2xl border border-slate-100 bg-slate-50/70 p-4 dark:border-[--admin-stroke] dark:bg-[--admin-surface-muted]">
                            {{ $this->form }}

                            <div class="mt-4 flex justify-end">
                                <x-filament::button color="primary" wire:click="submit" icon="heroicon-o-check-circle">
                                    Guardar condición
                                </x-filament::button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- CARD: IMPORTAR VARIAS CONDICIONES --}}
                <div
                    class="flex flex-col gap-5 rounded-3xl border-2 border-slate-100 bg-white p-8 shadow-md dark:border-[--admin-stroke] dark:bg-[--admin-surface]">
                    <div class="space-y-4 text-center">
                        <div
                            class="mb-2 flex h-20 w-20 items-center justify-center rounded-full bg-blue-100 text-blue-600 dark:bg-blue-900/20 dark:text-blue-500">
                            <x-filament::icon icon="heroicon-o-arrow-up-tray" class="h-10 w-10" />
                        </div>

                        <div class="space-y-1">
                            <h2 class="text-2xl font-semibold text-slate-900 dark:text-white">Subir Varias</h2>
                            <p class="text-sm text-slate-600 dark:text-[--admin-text-muted]">
                                Carga múltiples condiciones desde un archivo.
                            </p>
                        </div>

                        <p class="text-sm text-slate-600 dark:text-[--admin-text-muted]">
                            Usa el módulo de importación para registrar muchas SIM de una sola vez usando la plantilla.
                        </p>
                    </div>

                    <div class="mt-2 space-y-4">
                        <div class="mt-2 space-y-4">
                            {{-- BOTONES DE CARGA MASIVA --}}
                            <x-filament::button color="gray" class="w-full justify-center gap-2"
                                wire:click="downloadTemplate" icon="heroicon-o-arrow-down-tray">
                                Descargar plantilla
                            </x-filament::button>

                            <div class="rounded-xl border border-dashed border-slate-300 p-4 dark:border-slate-600">
                                {{ $this->bulkForm }}

                                <div class="mt-4">
                                    <x-filament::button wire:click="processBulkUpload" class="w-full justify-center"
                                        color="primary" icon="heroicon-o-cloud-arrow-up">
                                        Cargar archivo
                                    </x-filament::button>
                                </div>
                            </div>

                            <div
                                class="rounded-2xl border-2 border-dashed border-primary-200 bg-primary-50/60 p-4 text-left dark:border-[--admin-stroke] dark:bg-[--admin-surface-muted]">
                                <div class="flex items-start gap-3">
                                    <div
                                        class="mt-1 flex h-10 w-10 items-center justify-center rounded-full bg-white text-primary-600 shadow-sm dark:bg-[--admin-surface] dark:text-[--admin-primary]">
                                        <x-filament::icon icon="heroicon-o-information-circle" class="h-6 w-6" />
                                    </div>
                                    <div class="space-y-1">
                                        <p class="text-sm font-semibold text-primary-800 dark:text-[--admin-text]">Notas
                                        </p>
                                        <p class="text-xs text-primary-700 dark:text-[--admin-text-muted]">
                                            • El ICCID debe ser único para evitar duplicados.
                                        </p>
                                        <p class="text-xs text-primary-700 dark:text-[--admin-text-muted]">
                                            • El teléfono es opcional; el IDPOS y residual son requeridos.
                                        </p>
                                        <p class="text-xs text-primary-700 dark:text-[--admin-text-muted]">
                                            • El archivo se procesará inmediatamente.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
</x-filament-panels::page>