@php
    use Illuminate\Support\Number;
    $hasStores = count($stores) > 0;
@endphp

<x-filament-panels::page>
    <div class="space-y-6 text-[var(--retail-text)]">
        {{-- Header + saldo --}}
        <div class="rounded-3xl border border-[var(--retail-stroke)] bg-gradient-to-br from-[var(--retail-primary-strong)] via-[var(--retail-primary)] to-[var(--retail-primary-strong)] px-6 py-5 shadow-lg text-white">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-[0.25em]">Panel tendero</p>
                    <h1 class="text-3xl font-bold">Resumen general</h1>
                    <p class="text-sm text-white/80">Saldo disponible, tiendas vinculadas y actividad reciente.</p>
                    <div class="flex flex-wrap gap-2">
                        <x-filament::badge color="{{ $hasStores ? 'primary' : 'gray' }}" class="text-xs bg-white/10 text-white">
                            {{ auth('retailer')->user()?->name ?? 'Tendero' }}
                        </x-filament::badge>
                        <x-filament::badge color="gray" class="text-xs bg-white/10 text-white">
                            {{ $hasStores ? count($stores) . ' tienda(s)' : 'Sin tiendas' }}
                        </x-filament::badge>
                    </div>
                </div>

                <div class="flex flex-col gap-3">
                    <div class="rounded-2xl border border-white/30 bg-white/10 px-5 py-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide">Saldo total disponible</p>
                        <p class="text-3xl font-bold">
                            {{ Number::currency($userTotalBalance, 'COP') }}
                        </p>
                        <p class="text-xs text-white/80">Incluye todas tus tiendas y resta redenciones aprobadas.</p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <x-filament::button tag="a" size="sm" color="primary" icon="heroicon-o-building-storefront" href="{{ route('filament.retailer.pages.my-stores') }}" class="bg-white text-[var(--retail-primary-strong)] border-transparent hover:bg-white/90">
                            Ver todas las tiendas
                        </x-filament::button>
                        <x-filament::button tag="a" size="sm" color="primary" icon="heroicon-o-shopping-bag" href="{{ \App\Domain\Retailer\Filament\Pages\StoreCatalogPage::getUrl(panel: 'retailer') }}" :disabled="! $hasStores" class="bg-white text-[var(--retail-primary-strong)] border-transparent hover:bg-white/90">
                            Catálogo de productos
                        </x-filament::button>
                        <x-filament::button tag="a" size="sm" color="primary" icon="heroicon-o-ticket" href="{{ \App\Domain\Retailer\Filament\Resources\RedemptionResource::getUrl('index', panel: 'retailer') }}" :disabled="! $hasStores" class="bg-white text-[var(--retail-primary-strong)] border-transparent hover:bg-white/90">
                            Mis redenciones
                        </x-filament::button>
                        <x-filament::button tag="a" size="sm" color="primary" icon="heroicon-o-document-currency-dollar" href="{{ \App\Domain\Retailer\Filament\Pages\StoreStatementPage::getUrl(panel: 'retailer', parameters: ['store' => $stores[0]['id'] ?? null, 'period' => now()->format('Y-m')]) }}" :disabled="! $hasStores" class="bg-white text-[var(--retail-primary-strong)] border-transparent hover:bg-white/90">
                            Estado de cuenta
                        </x-filament::button>
                    </div>
                </div>
            </div>
        </div>

        @if (! $hasStores)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-amber-800 shadow-sm">
                No tienes tiendas asociadas. Contacta a soporte para vincularte y ver tu saldo.
            </div>
        @else
            {{-- Mis tiendas --}}
            <div class="rounded-2xl border border-[var(--retail-stroke)] bg-[var(--retail-surface)] px-5 py-4 shadow-sm">
                <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold">Mis tiendas</h2>
                        <p class="text-sm text-[var(--retail-text-muted)]">ID_PDV, ruta, circuito y ubicación.</p>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($stores as $store)
                        <div class="flex h-full flex-col gap-2 rounded-2xl border border-[var(--retail-stroke)] bg-[var(--retail-surface-muted)] p-4 shadow-sm">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-xs uppercase tracking-[0.15em] text-[var(--retail-primary)]">
                                        ID_PDV {{ $store['idpos'] ?? 'N/D' }}
                                    </p>
                                    <p class="text-lg font-semibold">{{ $store['name'] ?? 'Tienda' }}</p>
                                    <p class="text-xs text-[var(--retail-text-muted)]">
                                        Ruta {{ $store['route_code'] ?? 'N/D' }} · Circuito {{ $store['circuit_code'] ?? 'N/D' }}
                                    </p>
                                </div>
                                <span class="rounded-full bg-[var(--retail-primary-soft)] px-2 py-1 text-xs font-semibold text-[var(--retail-primary-strong)]">
                                    {{ Number::currency($store['balance'] ?? 0, 'COP') }}
                                </span>
                            </div>
                            <p class="text-sm text-[var(--retail-text)]">
                                {{ $store['address'] ?? 'Dirección no registrada' }}
                            </p>
                            <p class="text-xs text-[var(--retail-text-muted)]">
                                {{ $store['municipality'] ?? 'Municipio' }} @if (!empty($store['neighborhood'])) · {{ $store['neighborhood'] }} @endif
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Últimos movimientos y redenciones --}}
            <div class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-2xl border border-[var(--retail-stroke)] bg-[var(--retail-surface)] px-5 py-4 shadow-sm">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-lg font-semibold">Últimos movimientos</h2>
                        <p class="text-xs text-[var(--retail-text-muted)]">Liquidaciones y redenciones registradas</p>
                    </div>

                    @if (count($movements))
                        <div class="divide-y divide-[var(--retail-stroke)]">
                            @foreach ($movements as $movement)
                                @php $amount = (float) ($movement['amount'] ?? 0); @endphp
                                <div class="flex items-center justify-between gap-3 py-3">
                                    <div class="space-y-1">
                                        <p class="text-xs text-[var(--retail-text-muted)]">{{ $movement['date'] }}</p>
                                        <p class="text-sm font-semibold">{{ $movement['detail'] ?? ucfirst($movement['type'] ?? '') }}</p>
                                        <p class="text-xs text-[var(--retail-text-muted)]">
                                            {{ $movement['store_name'] ?? 'Tienda' }} ({{ $movement['store_idpos'] ?? 'ID_PDV' }})
                                        </p>
                                    </div>
                                    <div class="text-right text-sm font-semibold {{ $amount >= 0 ? 'text-[var(--retail-success)]' : 'text-[var(--retail-danger)]' }}">
                                        {{ Number::currency($amount, 'COP') }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-[var(--retail-text-muted)]">Aún no hay movimientos registrados.</p>
                    @endif
                </div>

                <div class="rounded-2xl border border-[var(--retail-stroke)] bg-[var(--retail-surface)] px-5 py-4 shadow-sm">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-lg font-semibold">Redenciones recientes</h2>



                    </div>

                    @if (count($recentRedemptions))
                        <div class="space-y-3">
                            @foreach ($recentRedemptions as $row)
                                <div class="rounded-xl border border-[var(--retail-stroke)] bg-[var(--retail-surface-muted)] px-4 py-3 shadow-sm">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-xs text-[var(--retail-text-muted)]">{{ $row['date'] }}</p>
                                            <p class="text-sm font-semibold">{{ $row['product'] }}</p>
                                            <p class="text-xs text-[var(--retail-text-muted)]">
                                                {{ $row['store'] ?? 'Tienda' }} ({{ $row['idpos'] ?? 'ID_PDV' }})
                                            </p>
                                        </div>
                                        <div class="text-sm font-semibold text-[var(--retail-primary)]">
                                            {{ Number::currency($row['total'] ?? 0, 'COP') }}
                                        </div>
                                    </div>
                                    <p class="mt-1 text-xs capitalize text-[var(--retail-text-muted)]">{{ $row['status'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-[var(--retail-text-muted)]">Sin redenciones aún.</p>
                    @endif
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
