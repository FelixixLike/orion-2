<x-filament-panels::page>
    <div class="admin-dashboard space-y-6">
        {{-- Hero principal --}}
        <div class="admin-dashboard-hero">
            <div class="admin-dashboard-hero__content">
                <div class="admin-dashboard-hero__headline">
                    <p class="admin-dashboard-eyebrow">Panel de Administracion</p>
                    <h1>Escritorio general</h1>
                    <p class="admin-dashboard-subtitle">
                        Resumen de tiendas, liquidaciones, redenciones e importaciones.
                    </p>
                    <div class="admin-dashboard-badges">
                        <x-filament::badge class="text-xs bg-white/10 text-white">
                            {{ auth('admin')->user()?->getFilamentName() }}
                        </x-filament::badge>
                        @if ($roleSummary)
                            <x-filament::badge class="text-xs bg-white/10 text-white">
                                Roles: {{ $roleSummary }}
                            </x-filament::badge>
                        @endif
                    </div>
                </div>

                <div class="admin-dashboard-hero__aside">
                    <div class="admin-dashboard-hero__stats">
                        <p class="label">Estado general del mes</p>
                        <div class="stats-grid">
                            <div>
                                <p class="muted">Tiendas activas</p>
                                <p class="value">{{ $activeStores }}</p>
                            </div>
                            <div>
                                <p class="muted">Tenderos activos</p>
                                <p class="value">{{ $tenderersCount }}</p>
                            </div>
                            <div>
                                <p class="muted">Liquidaciones este mes</p>
                                <p class="value">{{ $monthLiquidations }}</p>
                            </div>
                            <div>
                                <p class="muted">Redenciones pendientes</p>
                                <p class="value">{{ $pendingRedemptions }}</p>
                            </div>
                        </div>
                        <p class="footer">Indicadores globales de operacion.</p>
                    </div>
                    <div class="admin-dashboard-actions">
                        <x-filament::button tag="a" size="sm" color="primary" icon="heroicon-o-building-storefront"
                            href="{{ \App\Domain\Admin\Filament\Resources\StoreResource::getUrl('index', panel: 'admin') }}"
                            class="admin-dashboard-pill">
                            Ver tiendas
                        </x-filament::button>

                        @if(auth('admin')->user()?->hasPermissionTo('imports.view', 'admin'))
                            <x-filament::button tag="a" size="sm" color="primary" icon="heroicon-o-arrow-down-tray"
                                href="{{ \App\Domain\Admin\Filament\Pages\ImportModulePage::getUrl(panel: 'admin') }}"
                                class="admin-dashboard-pill">
                                Importaciones
                            </x-filament::button>
                        @endif

                        <x-filament::button tag="a" size="sm" color="primary" icon="heroicon-o-gift"
                            href="{{ \App\Domain\Admin\Filament\Resources\RedemptionProductResource::getUrl('index', panel: 'admin') }}"
                            class="admin-dashboard-pill">
                            Productos redimibles
                        </x-filament::button>

                        @if(auth('admin')->user()?->hasPermissionTo('redemptions.view', 'admin'))
                            <x-filament::button tag="a" size="sm" color="primary" icon="heroicon-o-ticket"
                                href="{{ \App\Domain\Admin\Filament\Resources\RedemptionResource::getUrl('index', panel: 'admin') }}"
                                class="admin-dashboard-pill">
                                Redenciones
                            </x-filament::button>
                        @endif
                    </div>
                </div>
            </div>
        </div>

            {{-- Resumen tiendas / catalogo --}}
            <div class="grid gap-4 lg:grid-cols-3">
                <x-filament::card class="admin-dashboard-card lg:col-span-2">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="section-title">Tiendas recientes</h2>
                        <p class="section-subtitle">Ultimas tiendas creadas o actualizadas.</p>
                    </div>

                    @if (count($recentStores))
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                                <thead class="bg-slate-50 dark:bg-slate-900/40">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-600">
                                            ID_PDV</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-600">
                                            Tienda</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-600">Ruta
                                        </th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-600">
                                            Circuito</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-600">
                                            Estado</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                                    @foreach ($recentStores as $store)
                                        <tr>
                                            <td class="px-3 py-2 text-xs text-slate-700 dark:text-slate-200">{{ $store->idpos }}
                                            </td>
                                            <td class="px-3 py-2 text-sm font-semibold text-slate-900 dark:text-slate-100">
                                                {{ $store->name }}</td>
                                            <td class="px-3 py-2 text-xs text-slate-600">{{ $store->route_code ?? '-' }}</td>
                                            <td class="px-3 py-2 text-xs text-slate-600">{{ $store->circuit_code ?? '-' }}</td>
                                            <td class="px-3 py-2 text-xs capitalize text-slate-700">
                                                {{ $store->status?->label() ?? $store->status }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-sm text-slate-500">Aun no hay tiendas registradas.</p>
                    @endif
                </x-filament::card>

                <x-filament::card class="admin-dashboard-card">
                    <h2 class="mb-3 section-title">Resumen de catalogo</h2>
                    <dl class="space-y-2 text-sm text-slate-600 dark:text-slate-300">
                        <div class="flex items-center justify-between">
                            <dt>Productos redimibles activos</dt>
                            <dd class="font-semibold text-slate-900 dark:text-slate-100">{{ $activeProductsCount }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt>Importaciones en curso</dt>
                            <dd class="font-semibold text-slate-900 dark:text-slate-100">{{ $importsInProgress }}</dd>
                        </div>
                    </dl>
                    <p class="mt-3 text-xs text-slate-500">
                        Usa los modulos de Importacion y Productos redimibles para administrar los datos operativos del
                        mes.
                    </p>
                </x-filament::card>
            </div>

            {{-- Liquidaciones y redenciones --}}
            <div class="grid gap-4 lg:grid-cols-2">
                <x-filament::card class="admin-dashboard-card">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="section-title">Ultimas liquidaciones</h2>
                        <p class="section-subtitle">Ordenadas por fecha de creacion.</p>
                    </div>
                    @if($recentLiquidations->count())
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                                <thead class="bg-slate-50 dark:bg-slate-900/40">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-600">
                                            Tienda</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-600">
                                            Periodo</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-600">Neto
                                        </th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-600">
                                            Estado</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                                    @foreach($recentLiquidations as $row)
                                        <tr>
                                            <td class="px-3 py-2">{{ $row->store->name ?? 'Tienda' }}</td>
                                            <td class="px-3 py-2">
                                                {{ sprintf('%02d/%s', $row->period_month, $row->period_year) }}</td>
                                            <td class="px-3 py-2 font-semibold">
                                                {{ \Illuminate\Support\Number::currency($row->net_amount, 'COP') }}
                                            </td>
                                            <td class="px-3 py-2 capitalize">{{ $row->status }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-sm text-slate-500">Aun no hay liquidaciones registradas.</p>
                    @endif
                </x-filament::card>

                <x-filament::card class="admin-dashboard-card">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="section-title">Ultimas redenciones</h2>
                        <p class="section-subtitle">Incluye aprobadas, confirmadas y entregadas.</p>
                    </div>
                    @if($recentRedemptions->count())
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                                <thead class="bg-slate-50 dark:bg-slate-900/40">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-600">
                                            Tienda</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-600">
                                            Producto</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-600">Total
                                        </th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-600">
                                            Estado</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                                    @foreach($recentRedemptions as $row)
                                        <tr>
                                            <td class="px-3 py-2">{{ $row->store->name ?? 'Tienda' }}</td>
                                            <td class="px-3 py-2">{{ $row->redemptionProduct->name ?? 'Producto' }}</td>
                                            <td class="px-3 py-2 font-semibold">
                                                {{ \Illuminate\Support\Number::currency($row->total_value, 'COP') }}
                                            </td>
                                            <td class="px-3 py-2 capitalize">{{ $row->status }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-sm text-slate-500">Aun no hay redenciones registradas.</p>
                    @endif
                </x-filament::card>
            </div>

            {{-- Importaciones recientes --}}
            @if (count($recentImports))
                <x-filament::card class="admin-dashboard-card">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="section-title">Importaciones recientes</h2>
                        <p class="section-subtitle">Ultimos archivos procesados por el modulo de Importacion.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                            <thead class="bg-slate-50 dark:bg-slate-900/40">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-600">Archivo
                                    </th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-600">Tipo</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-600">Estado
                                    </th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-600">Filas
                                    </th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-600">Creado
                                        por</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-600">Fecha
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                                @foreach ($recentImports as $import)
                                    <tr>
                                        <td class="px-3 py-2">{{ $import->file }}</td>
                                        <td class="px-3 py-2 text-xs uppercase text-slate-600">{{ $import->type }}</td>
                                        <td class="px-3 py-2 text-xs capitalize text-slate-700">{{ $import->status }}</td>
                                        <td class="px-3 py-2 text-xs text-slate-700">
                                            {{ $import->processed_rows }}/{{ $import->total_rows }}
                                        </td>
                                        <td class="px-3 py-2 text-xs text-slate-700">
                                            {{ $import->creator?->getFilamentName() ?? 'Sistema' }}
                                        </td>
                                        <td class="px-3 py-2 text-xs text-slate-700">
                                            {{ optional($import->created_at)->format('Y-m-d H:i') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-filament::card>
            @endif
        </div>
</x-filament-panels::page>