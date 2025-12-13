<x-filament-panels::page>
    <div
        class="min-h-[75vh] bg-gradient-to-b from-red-50/60 via-white to-white px-4 py-8 dark:bg-none dark:bg-[--admin-surface-muted] sm:px-6 lg:px-10">
        <div class="mx-auto max-w-6xl space-y-8">
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold text-red-600 dark:text-[--admin-primary]">Crear Tienda</h1>
                <p class="text-sm text-slate-600 dark:text-[--admin-text-muted]">
                    Registra una tienda individual o realiza la carga masiva desde esta misma pantalla.
                </p>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                {{-- Columna: Registro individual --}}
                <div
                    class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-md dark:border-[--admin-stroke] dark:bg-[--admin-surface]">
                    <div class="mb-5 flex items-center gap-3">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-full bg-primary-100 text-primary-600 dark:bg-primary-900/20 dark:text-primary-400">
                            <x-filament::icon icon="heroicon-o-building-storefront" class="h-7 w-7" />
                        </div>
                        <div>
                            <h2 class="text-xl font-semibold text-slate-900 dark:text-white">Registro Individual</h2>
                            <p class="text-sm text-slate-600 dark:text-[--admin-text-muted]">
                                Completa el formulario y crea la tienda al instante.
                            </p>
                        </div>
                    </div>

                    <form wire:submit.prevent="create" class="space-y-4">
                        <div
                            class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 p-4 dark:border-[--admin-stroke] dark:bg-[--admin-surface-muted]">
                            {{ $this->form }}
                        </div>

                        <div class="flex justify-end">
                            <x-filament::button type="submit" color="primary" class="w-full sm:w-auto">
                                Crear Tienda
                            </x-filament::button>
                        </div>
                    </form>
                </div>

                {{-- Columna: Carga masiva --}}
                <div
                    class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-md dark:border-[--admin-stroke] dark:bg-[--admin-surface]">
                    <div class="mb-5 flex items-center gap-3">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-full bg-green-100 text-green-600 dark:bg-green-900/20 dark:text-green-400">
                            <x-filament::icon icon="heroicon-o-arrow-up-tray" class="h-7 w-7" />
                        </div>
                        <div>
                            <h2 class="text-xl font-semibold text-slate-900 dark:text-white">Carga Masiva</h2>
                            <p class="text-sm text-slate-600 dark:text-[--admin-text-muted]">
                                Sube el archivo Excel de tiendas y tenderos.
                            </p>
                        </div>
                    </div>

                    <div class="space-y-6">
                        <div
                            class="rounded-2xl border border-slate-100 bg-slate-50/70 p-4 dark:border-[--admin-stroke] dark:bg-[--admin-surface-muted]">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white">Plantilla</p>
                                    <p class="text-xs text-slate-600 dark:text-[--admin-text-muted]">
                                        Descarga la plantilla necesaria para la importacion.
                                    </p>
                                </div>
                                <x-filament::button type="button" color="gray" size="sm"
                                    icon="heroicon-o-arrow-down-tray" wire:click="downloadTemplate">
                                    Descargar Plantilla
                                </x-filament::button>
                            </div>
                        </div>

                        <form wire:submit.prevent="processBulkUpload" class="space-y-4">
                            <div class="space-y-2">
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">Cargar Archivo Excel</p>
                                {{ $this->bulkForm }}
                            </div>

                            <x-filament::button type="submit" color="success" icon="heroicon-o-cloud-arrow-up"
                                class="w-full justify-center">
                                Procesar Carga Masiva
                            </x-filament::button>
                        </form>

                        @if (!empty($this->conflicts))
                            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 shadow-inner">
                                <div class="mb-3 flex items-center justify-between">
                                    <div>
                                        <p class="font-semibold">Conflictos detectados ({{ count($this->conflicts) }})</p>
                                        <p class="text-amber-800">Revisa y aplica/omite los cambios.</p>
                                    </div>
                                </div>
                                <div class="space-y-3">
                                    @foreach ($this->conflicts as $idx => $conflict)
                                        <div class="rounded-xl border border-amber-200 bg-white p-3 shadow-sm">
                                            <div class="flex flex-wrap items-start justify-between gap-2">
                                                <div class="space-y-1">
                                                    <p class="text-xs uppercase tracking-wide text-amber-700">
                                                        {{ $conflict['type'] ?? 'conflicto' }}
                                                    </p>
                                                    <p class="font-semibold text-slate-900">
                                                        {{ $conflict['message'] ?? 'Revisar conflicto' }}
                                                    </p>
                                                    <p class="text-xs text-slate-600">
                                                        ID_PDV: {{ $conflict['idpos'] ?? 'N/A' }}
                                                        @if (!empty($conflict['id_number'])) | Cédula: {{ $conflict['id_number'] }} @endif
                                                    </p>
                                                    @if (!empty($conflict['existing']) || !empty($conflict['incoming']))
                                                        <div class="mt-1 grid gap-2 text-xs text-slate-700 sm:grid-cols-2">
                                                            @if (!empty($conflict['existing']))
                                                                <div>
                                                                    <p class="font-semibold text-slate-800">Actual:</p>
                                                                    <pre class="whitespace-pre-wrap text-[11px] text-slate-700">{{ json_encode($conflict['existing'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                                </div>
                                                            @endif
                                                            @if (!empty($conflict['incoming']))
                                                                <div>
                                                                    <p class="font-semibold text-slate-800">Nuevo:</p>
                                                                    <pre class="whitespace-pre-wrap text-[11px] text-slate-700">{{ json_encode($conflict['incoming'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @endif
                                                </div>
                                                <div class="flex flex-col gap-2">
                                                    <x-filament::button color="success" size="sm"
                                                        wire:click="handleConflict({{ $idx }}, 'update')">
                                                        Aplicar
                                                    </x-filament::button>
                                                    <x-filament::button color="secondary" size="sm"
                                                        wire:click="handleConflict({{ $idx }}, 'omit')">
                                                        Omitir
                                                    </x-filament::button>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div
                            class="rounded-2xl bg-blue-50 p-4 text-sm text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                            <div class="flex items-start gap-2">
                                <x-filament::icon icon="heroicon-o-information-circle" class="mt-0.5 h-5 w-5 shrink-0" />
                                <div class="space-y-1">
                                    <span class="font-semibold">Nota:</span>
                                    <ul class="ml-4 list-disc space-y-1 text-xs">
                                        <li>Busca usuarios por documento; si no existen, los crea inactivos.</li>
                                        <li>Si el IDPDV ya existe, actualiza la informacion de la tienda.</li>
                                        <li>Crea rutas y circuitos automaticamente si son nuevos.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
