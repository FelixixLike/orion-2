@php
    use Illuminate\Support\Number;
    $hasStores = count($stores) > 0;

    $typeTranslations = [
        'all' => 'Todos',
        'sim' => 'SIM',
        'recharge' => 'Recarga',
        'device' => 'Dispositivo',
        'accessory' => 'Accesorio',
    ];
@endphp

<x-filament-panels::page>
    <div class="space-y-6 text-[var(--retail-text)]">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-[var(--retail-primary)]">Catálogo</p>
                <h1 class="text-2xl font-bold">Productos redimibles</h1>
                <p class="text-sm text-[var(--retail-text-muted)]">Selecciona tu tienda, revisa tu saldo y redime.</p>
            </div>
            <div
                class="rounded-2xl border border-[var(--retail-stroke)] bg-[var(--retail-primary-soft)] px-4 py-3 text-right shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-[var(--retail-primary-strong)]">Saldo
                    disponible</p>
                <p class="text-xl font-bold text-[var(--retail-primary-strong)]">
                    {{ Number::currency($availableBalance, 'COP') }}
                </p>
            </div>
        </div>

        <div
            class="flex flex-col gap-4 rounded-2xl border border-[var(--retail-stroke)] bg-[var(--retail-surface)] p-4 shadow">
            <div class="grid gap-3 md:grid-cols-3 md:items-center md:gap-4">
                <div class="md:col-span-2 relative">
                    <span class="pointer-events-none absolute left-3 top-2.5 text-[var(--retail-text-muted)]">
                        <x-filament::icon icon="heroicon-o-magnifying-glass" class="h-5 w-5" />
                    </span>
                    <input wire:model.debounce.400ms="search" type="search" placeholder="Buscar productos..."
                        class="w-full rounded-xl border border-[var(--retail-stroke)] bg-[var(--retail-surface-muted)] px-10 py-3 text-sm text-[var(--retail-text)] placeholder:text-[var(--retail-text-muted)] focus:border-[var(--retail-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--retail-primary)]/30" />
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-xs font-semibold text-[var(--retail-text-muted)]">Tienda para redimir</label>
                    <select wire:model.live="selectedStoreId"
                        class="w-full rounded-xl border border-[var(--retail-stroke)] bg-[var(--retail-surface-muted)] px-3 py-2 text-sm text-[var(--retail-text)] placeholder:text-[var(--retail-text-muted)] focus:border-[var(--retail-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--retail-primary)]/30">
                        @foreach ($stores as $store)
                            <option value="{{ $store['id'] ?? '' }}">
                                {{ $store['idpos'] ?? 'PDV' }} - {{ $store['name'] ?? 'Tienda' }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <button wire:click="$set('type','all')" @class([
                    'rounded-full px-3 py-1 text-sm font-semibold border transition',
                    $type === 'all'
                    ? 'bg-[var(--retail-primary)] text-white border-[var(--retail-primary-strong)]'
                    : 'bg-[var(--retail-surface-muted)] text-[var(--retail-text-muted)] border-[var(--retail-stroke)] hover:border-[var(--retail-primary)] hover:text-[var(--retail-text)]',
                ])>
                    {{ $typeTranslations['all'] }}
                </button>
                @foreach ($types as $option)
                    <button wire:click="$set('type','{{ $option }}')" @class([
                        'rounded-full px-3 py-1 text-sm font-semibold border transition capitalize',
                        $type === $option
                        ? 'bg-[var(--retail-primary)] text-white border-[var(--retail-primary-strong)]'
                        : 'bg-[var(--retail-surface-muted)] text-[var(--retail-text-muted)] border-[var(--retail-stroke)] hover:border-[var(--retail-primary)] hover:text-[var(--retail-text)]',
                    ])>
                        {{ $typeTranslations[$option] ?? $option }}
                    </button>
                @endforeach
            </div>
        </div>

        @if (!$hasStores)
            <div class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                No tienes tiendas asociadas. Vincula una tienda para redimir productos.
            </div>
        @elseif (count($products) === 0)
            <div
                class="rounded-xl border border-[var(--retail-stroke)] bg-[var(--retail-surface)] p-6 text-sm text-[var(--retail-text-muted)]">
                No hay productos activos para mostrar.
            </div>
        @else
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($products as $product)
                    <div
                        class="flex h-full flex-col overflow-hidden rounded-2xl border border-[var(--retail-stroke)] bg-[var(--retail-surface)] shadow-sm">
                        <div class="h-44 w-full bg-[var(--retail-surface-muted)]">
                            @if ($product['image_url'])
                                <img src="{{ $product['image_url'] }}" alt="{{ $product['name'] }}"
                                    class="h-full w-full object-cover">
                            @else
                                <div class="flex h-full items-center justify-center text-[var(--retail-text-muted)]">
                                    <x-filament::icon icon="heroicon-o-photo" class="h-10 w-10" />
                                </div>
                            @endif
                        </div>
                        <div class="flex flex-1 flex-col gap-2 p-4">
                            <div class="flex items-center justify-between">
                                <span
                                    class="rounded-full border border-[var(--retail-stroke)] bg-[var(--retail-primary-soft)] px-3 py-1 text-xs font-semibold uppercase text-[var(--retail-primary-strong)]">
                                    {{ $typeTranslations[$product['type']] ?? $product['type'] }}
                                </span>
                            </div>
                            <div>
                                <p class="text-lg font-semibold text-[var(--retail-text)]">{{ $product['name'] }}</p>
                                <p class="text-sm text-[var(--retail-text-muted)] line-clamp-2">{{ $product['description'] }}
                                </p>
                            </div>
                            <p class="text-base font-semibold text-[var(--retail-primary-strong)]">
                                {{ Number::currency($product['unit_value'], 'COP') }}
                            </p>
                            <div class="mt-auto">
                                <x-filament::button type="button"
                                    wire:click="redeem({{ $product['id'] }}, {{ $product['unit_value'] }})"
                                    icon="heroicon-o-shopping-cart" color="primary"
                                    class="w-full justify-center bg-[var(--retail-primary)] border-[var(--retail-primary-strong)] hover:bg-[var(--retail-primary-strong)]"
                                    :disabled="!$hasStores">
                                    Redimir
                                </x-filament::button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>