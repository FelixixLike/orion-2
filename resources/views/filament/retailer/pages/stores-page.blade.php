@php
    use Illuminate\Support\Number;
    $filteredStores = collect($stores)
        ->filter(function ($store) use ($filterRoute, $filterMunicipality) {
            $routeOk = $filterRoute ? ($store['route_code'] ?? null) === $filterRoute : true;
            $munOk = $filterMunicipality ? ($store['municipality'] ?? null) === $filterMunicipality : true;
            return $routeOk && $munOk;
        })
        ->values()
        ->all();
@endphp

<x-filament-panels::page>
    <div class="space-y-6 text-slate-900 dark:text-slate-100">
        <div
            class="overflow-hidden rounded-3xl border border-slate-200 bg-gradient-to-r from-white via-slate-50 to-white p-6 shadow-lg shadow-slate-200/60 dark:border-slate-800 dark:from-slate-950 dark:via-slate-900 dark:to-slate-950 dark:shadow-black/40">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="space-y-2">
                    <div
                        class="inline-flex items-center gap-2 rounded-full bg-indigo-500/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-indigo-600 dark:text-indigo-200">
                        Tiendas vinculadas
                        <span
                            class="rounded-full bg-indigo-500/20 px-2 py-0.5 text-indigo-50">{{ count($stores) }}</span>
                    </div>
                    <div class="flex flex-col gap-1">
                        <h1 class="text-3xl font-bold">Mis tiendas</h1>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Selecciona tu tienda para ver balances y
                            redenciones.</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <x-filament::button tag="a"
                        href="{{ route('filament.retailer.pages.balance', ['panel' => 'retailer']) }}" color="primary"
                        icon="heroicon-o-banknotes" size="sm">
                        Ir a balance
                    </x-filament::button>
                    <x-filament::button tag="a"
                        href="{{ \App\Domain\Retailer\Filament\Pages\StoreCatalogPage::getUrl(panel: 'retailer') }}"
                        color="gray" icon="heroicon-o-shopping-bag" size="sm">
                        Ver catálogo
                    </x-filament::button>
                </div>
            </div>

            {{-- Filtros --}}
            @if (count($stores))
                <div class="mt-4 flex flex-wrap gap-3">
                    <div>
                        <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Ruta</label>
                        <select wire:model="filterRoute"
                            class="w-52 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                            <option value="">Todas</option>
                            @foreach ($routes as $route)
                                <option value="{{ $route }}">{{ $route }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Municipio</label>
                        <select wire:model="filterMunicipality"
                            class="w-52 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                            <option value="">Todos</option>
                            @foreach ($municipalities as $mun)
                                <option value="{{ $mun }}">{{ $mun }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            @endif
        </div>

        @if (count($filteredStores) === 0)
            <div
                class="flex flex-col items-center justify-center gap-4 rounded-3xl border border-dashed border-slate-200 bg-white px-6 py-12 text-center text-slate-600 shadow-lg shadow-slate-200/50 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-300">
                <div
                    class="flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-900 dark:text-slate-300">
                    <x-filament::icon icon="heroicon-o-building-storefront" class="h-7 w-7" />
                </div>
                <div class="space-y-1">
                    <h2 class="text-lg font-semibold">Sin tienda asignada</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Tu usuario aún no tiene una tienda vinculada o los
                        filtros no tienen resultados.</p>
                </div>
                <x-filament::button tag="a" color="primary" icon="heroicon-o-envelope" href="mailto:soporte@orion.com"
                    size="sm">
                    Contactar soporte
                </x-filament::button>
            </div>
        @else
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($filteredStores as $storeItem)
                    @php $isActive = ($storeItem['id'] ?? null) === $activeStoreId; @endphp
                    <div
                        class="relative flex h-full flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-md shadow-slate-200/60 transition duration-200 hover:-translate-y-1 hover:border-indigo-200 hover:shadow-lg dark:border-slate-800 dark:bg-slate-900 dark:shadow-black/30">
                        @if ($isActive)
                            <span
                                class="absolute right-4 top-4 rounded-full bg-indigo-500/15 px-3 py-1 text-xs font-semibold text-indigo-600 dark:text-indigo-200">Seleccionada</span>
                        @endif

                        <div class="space-y-1">
                            <p class="text-xs uppercase tracking-[0.2em] text-indigo-500 dark:text-indigo-200">
                                ID_PDV {{ $storeItem['idpos'] ?? 'N/D' }}
                                @if (!empty($storeItem['route_code'])) • Ruta {{ $storeItem['route_code'] }} @endif
                                @if (!empty($storeItem['circuit_code'])) • Circuito {{ $storeItem['circuit_code'] }} @endif
                            </p>
                            <p class="text-xl font-semibold">{{ $storeItem['name'] ?? 'Tienda' }}</p>
                        </div>

                        <div class="space-y-1 text-sm text-slate-600 dark:text-slate-300">
                            <p class="leading-relaxed">{{ $storeItem['address'] ?? 'Dirección no registrada' }}</p>
                            <p class="text-slate-500 dark:text-slate-400">
                                {{ $storeItem['municipality'] ?? 'Municipio' }}
                                @if (!empty($storeItem['neighborhood']))
                                    • {{ $storeItem['neighborhood'] }}
                                @endif
                            </p>
                            <p class="text-slate-500 dark:text-slate-400">
                                Cat: {{ $storeItem['category'] ?? 'N/D' }} • Tel: {{ $storeItem['phone'] ?? 'N/D' }}
                                • <span
                                    class="{{ ($storeItem['status'] ?? '') === 'active' ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">{{ ($storeItem['status'] ?? '') === 'active' ? 'Activa' : 'Inactiva' }}</span>
                            </p>
                        </div>

                        <div class="mt-auto flex flex-wrap items-center gap-3">
                            <span class="text-sm font-semibold text-indigo-600 dark:text-indigo-200">Saldo:
                                {{ Number::currency($storeItem['balance'] ?? 0, 'COP') }}</span>
                            @if (!$isActive)
                                <form method="POST" action="{{ route('portal.active-store.update') }}" class="flex">
                                    @csrf
                                    <input type="hidden" name="store_id" value="{{ $storeItem['id'] }}">
                                    <x-filament::button type="submit" size="sm" color="primary" icon="heroicon-o-check-circle"
                                        class="shadow-indigo-900/30">
                                        Seleccionar
                                    </x-filament::button>
                                </form>
                            @else
                                <span class="text-xs font-semibold text-emerald-500">Usando esta tienda</span>
                            @endif
                            <x-filament::button tag="a" size="sm" color="gray" icon="heroicon-o-document-currency-dollar"
                                href="{{ \App\Domain\Retailer\Filament\Pages\StoreStatementPage::getUrl(panel: 'retailer', parameters: ['store' => $storeItem['id'], 'period' => now()->format('Y-m')]) }}">
                                Estado de cuenta
                            </x-filament::button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>