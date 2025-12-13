<x-filament-panels::page>
    <div class="min-h-[75vh] bg-gradient-to-b from-red-50/80 via-white to-white px-4 py-8 sm:px-6 lg:px-10">
        <div class="mx-auto max-w-7xl space-y-8">
            {{-- HEADER --}}
            <div class="space-y-4">
                <a
                    href="{{ $this->getStoresModuleUrl() }}"
                    class="inline-flex items-center gap-2 text-slate-700 hover:text-red-600 transition"
                >
                    <x-filament::icon icon="heroicon-s-arrow-left" class="w-5 h-5" />
                    Volver
                </a>

                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 class="text-2xl md:text-3xl font-semibold text-red-600">Lista de Tiendas</h1>
                        <p class="text-sm text-slate-600">Ver y gestionar todas las tiendas existentes</p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <x-filament::button
                            tag="a"
                            size="sm"
                            color="gray"
                            icon="heroicon-o-arrow-path"
                            href="{{ $this->getRefreshUrl() }}"
                        >
                            Actualizar
                        </x-filament::button>

                        @if ($this->getExportUrl() !== '#')
                            <x-filament::button
                                tag="a"
                                size="sm"
                                color="gray"
                                icon="heroicon-o-arrow-down-tray"
                                href="{{ $this->getExportUrl() }}"
                            >
                                Exportar
                            </x-filament::button>
                        @endif
                    </div>
                </div>
            </div>

            {{-- FILTROS --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-center gap-2 mb-4">
                    <x-filament::icon icon="heroicon-o-funnel" class="w-4 h-4 text-red-600" />
                    <span class="text-slate-700 text-sm font-medium">Filtros</span>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-5">
                    {{-- BUSCADOR --}}
                    <div class="relative lg:col-span-2">
                        <x-filament::icon icon="heroicon-o-magnifying-glass"
                            class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
                        <input
                            type="search"
                            name="search"
                            value="{{ $filters['search'] ?? '' }}"
                            placeholder="Buscar por nombre, ID, documento..."
                            class="w-full rounded-lg border border-slate-300 bg-white py-2.5 pl-9 pr-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-500/30"
                        />
                    </div>

                    {{-- CATEGORÍA --}}
                    <div>
                        <select
                            name="category"
                            class="w-full rounded-lg border border-slate-300 bg-white py-2.5 px-3 text-sm text-slate-900 focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-500/30"
                        >
                            <option value="">Todas las categorías</option>
                            <option value="PLATINO" @selected(($filters['category'] ?? '') === 'PLATINO')>Platino</option>
                            <option value="ORO" @selected(($filters['category'] ?? '') === 'ORO')>Oro</option>
                            <option value="PLATA" @selected(($filters['category'] ?? '') === 'PLATA')>Plata</option>
                        </select>
                    </div>

                    {{-- ESTADO --}}
                    <div>
                        <select
                            name="status"
                            class="w-full rounded-lg border border-slate-300 bg-white py-2.5 px-3 text-sm text-slate-900 focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-500/30"
                        >
                            <option value="">Todos los estados</option>
                            <option value="active" @selected(($filters['status'] ?? '') === 'active')>Activos</option>
                            <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactivos</option>
                        </select>
                    </div>

                    {{-- MUNICIPIO --}}
                    <div>
                        <select
                            name="municipio"
                            class="w-full rounded-lg border border-slate-300 bg-white py-2.5 px-3 text-sm text-slate-900 focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-500/30"
                        >
                            <option value="">Todos los municipios</option>
                            @foreach(($municipios ?? []) as $codigo => $nombre)
                                <option value="{{ $codigo }}" @selected(($filters['municipio'] ?? '') == $codigo)>
                                    {{ $nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- FILTRO RUTA --}}
                <div class="mt-4">
                    <select
                        name="route"
                        class="w-full rounded-lg border border-slate-300 bg-white py-2.5 px-3 text-sm text-slate-900 focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-500/30 md:w-64"
                    >
                        <option value="">Todas las rutas</option>
                        @foreach(($routes ?? []) as $route)
                            <option value="{{ $route }}" @selected(($filters['route'] ?? '') == $route)>
                                {{ $route }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- CONTADOR RESULTADOS --}}
            @php
                $total = $totalCount ?? (is_countable($stores ?? null) ? count($stores) : 0);
                $shown = $filteredCount ?? $total;
            @endphp
            <div class="text-sm text-slate-600">
                Mostrando <span class="text-red-600 font-semibold">{{ $shown }}</span>
                de {{ $total }} tiendas
            </div>

            {{-- TABLA --}}
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm text-slate-700">
                        <thead class="bg-red-50 text-xs font-semibold uppercase text-slate-700">
                            <tr>
                                <th class="px-4 py-3">ID PDV</th>
                                <th class="px-4 py-3">Documento</th>
                                <th class="px-4 py-3">Nombre Punto</th>
                                <th class="px-4 py-3">Propietario</th>
                                <th class="px-4 py-3">Usuario</th>
                                <th class="px-4 py-3">Categoría</th>
                                <th class="px-4 py-3">Ruta</th>
                                <th class="px-4 py-3">Teléfono</th>
                                <th class="px-4 py-3">Municipio</th>
                                <th class="px-4 py-3">Barrio</th>
                                <th class="px-4 py-3">Dirección</th>
                                <th class="px-4 py-3">Correo</th>
                                <th class="px-4 py-3">Saldo</th>
                                <th class="px-4 py-3">Activo</th>
                                <th class="px-4 py-3">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse(($stores ?? []) as $store)
                                <tr class="border-t border-slate-100 hover:bg-red-50/40">
                                    <td class="px-4 py-3">{{ $store->id_pdv ?? $store->id_pdv ?? '-' }}</td>
                                    <td class="px-4 py-3">{{ $store->nro_documento ?? '-' }}</td>
                                    <td class="px-4 py-3">
                                        <div class="min-w-[150px]">
                                            {{ $store->nombre_punto ?? '-' }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="min-w-[180px]">
                                            {{ $store->nombre_cliente ?? '-' }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center rounded-full bg-slate-50 px-2.5 py-0.5 text-xs font-semibold text-slate-700">
                                            {{ $store->usuario ?? '-' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        @php
                                            $cat = $store->categoria ?? '';
                                            $catClass = match ($cat) {
                                                'PLATINO' => 'bg-purple-100 text-purple-700',
                                                'ORO'     => 'bg-yellow-100 text-yellow-700',
                                                'PLATA'   => 'bg-slate-100 text-slate-700',
                                                default   => 'bg-slate-100 text-slate-700',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $catClass }}">
                                            {{ $cat ?: 'N/D' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">{{ $store->ruta ?? '-' }}</td>
                                    <td class="px-4 py-3">{{ $store->celular ?? '-' }}</td>
                                    <td class="px-4 py-3">
                                        <div class="min-w-[120px]">
                                            {{ $store->municipio_nombre ?? $store->municipio ?? $store->municipio_codigo_dane ?? '-' }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">{{ $store->barrio ?? '-' }}</td>
                                    <td class="px-4 py-3">
                                        <div class="min-w-[150px]">
                                            {{ $store->direccion ?? '-' }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="min-w-[180px]">
                                            {{ $store->correo_electronico ?? '-' }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        @php
                                            $saldo = $store->saldo ?? 0;
                                        @endphp
                                        <span class="{{ $saldo > 0 ? 'text-emerald-600' : 'text-red-600' }}">
                                            {{ Number::currency($saldo, 'COP', 'es_CO') }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        @php
                                            $active = (bool) ($store->activo ?? $store->active ?? false);
                                        @endphp
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $active ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                                            {{ $active ? 'Sí' : 'No' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-filament::button
                                            size="xs"
                                            color="gray"
                                            icon="heroicon-o-pencil-square"
                                            tag="a"
                                            href="{{ $this->getStoreEditUrl($store) }}"
                                        >
                                            Editar
                                        </x-filament::button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="15" class="px-4 py-8 text-center text-sm text-slate-500">
                                        No se encontraron tiendas con los filtros aplicados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- PAGINACIÓN (opcional si usas LengthAwarePaginator) --}}
            @if(isset($stores) && $stores instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                <div>
                    {{ $stores->links() }}
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
