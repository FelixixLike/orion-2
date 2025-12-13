@php
    use Illuminate\Support\Number;

    $typeLabels = [
        'sim' => 'SIM',
        'device' => 'Dispositivo',
        'accessory' => 'Accesorio',
        'recharge' => 'Recarga',
    ];

    $productType = $product['type'] ?? null;
    $selectedStore = collect($stores)->firstWhere('id', $storeId);
@endphp

<x-filament-panels::page>
    <div class="mx-auto max-w-4xl space-y-6 text-[var(--retail-text)]">
        <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.25em] text-[var(--retail-primary)]">Nueva redención</p>
                <h1 class="text-2xl font-semibold">Confirmar redención</h1>
                <p class="text-sm text-[var(--retail-text-muted)]">Revisa la información del producto, el saldo y envía la solicitud.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-filament::button tag="a" color="gray" icon="heroicon-o-shopping-bag"
                    href="{{ \App\Domain\Retailer\Filament\Pages\StoreCatalogPage::getUrl(panel: 'retailer') }}">
                    Volver al catálogo
                </x-filament::button>
                <x-filament::button tag="a" color="primary" icon="heroicon-o-queue-list"
                    href="{{ \App\Domain\Retailer\Filament\Resources\RedemptionResource::getUrl('index', panel: 'retailer') }}">
                    Mis redenciones
                </x-filament::button>
            </div>
        </div>

        @if (!$product)
            <div class="rounded-2xl border border-dashed border-[var(--retail-stroke)] bg-white px-6 py-6 text-center text-sm text-[var(--retail-text-muted)]">
                <p class="text-base font-semibold text-[var(--retail-text)]">El producto no está disponible.</p>
                <p>Regresa al catálogo para seleccionar un producto válido e intenta nuevamente.</p>
            </div>
        @else
            <div class="grid gap-5 lg:grid-cols-[220px_1fr]">
                <div class="space-y-3 rounded-2xl border border-[var(--retail-stroke)] bg-white/70 p-4 shadow-sm">
                    <div class="flex gap-3">
                        <div class="overflow-hidden rounded-lg border border-[var(--retail-stroke)] bg-[var(--retail-surface-muted)]" style="width: 160px; height: 110px;">
                            @if (!empty($product['image_url']))
                                <img src="{{ $product['image_url'] }}" alt="{{ $product['name'] }}"
                                    class="h-full w-full object-contain" />
                            @else
                                <div class="flex h-full items-center justify-center text-[var(--retail-text-muted)]">
                                    <x-filament::icon icon="heroicon-o-photo" class="h-6 w-6" />
                                </div>
                            @endif
                        </div>
                        <div class="flex-1 space-y-1">
                            <div class="flex items-center gap-2 text-[11px] uppercase tracking-wide text-[var(--retail-text-muted)]">
                                <span class="rounded-full bg-[var(--retail-primary-soft)] px-2 py-0.5 text-[var(--retail-primary-strong)]">
                                    {{ $typeLabels[$productType] ?? ucfirst($productType ?? 'Producto') }}
                                </span>
                                @if ($product['stock'] !== null)
                                    <span>Stock: <strong>{{ $product['stock'] }}</strong></span>
                                @endif
                            </div>
                            <p class="text-base font-semibold leading-tight">{{ $product['name'] ?? 'Producto' }}</p>
                            <p class="text-xs text-[var(--retail-text-muted)] leading-snug">
                                {{ $product['description'] ?? 'Producto disponible para redención.' }}
                            </p>
                        </div>
                    </div>
                    <div class="space-y-2 text-xs text-[var(--retail-text-muted)]">
                        <div class="flex items-center justify-between rounded-lg border border-[var(--retail-stroke)] px-3 py-2 text-sm">
                            <span class="uppercase tracking-[0.2em] text-[11px]">Valor unitario</span>
                            <span class="font-semibold text-[var(--retail-primary-strong)]">
                                {{ Number::currency($product['unit_value'] ?? 0, 'COP') }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between rounded-lg border border-[var(--retail-stroke)] px-3 py-2">
                            <span class="uppercase tracking-[0.2em] text-[11px]">Tipo</span>
                            <span class="font-semibold text-[var(--retail-text)]">
                                {{ $typeLabels[$productType] ?? ucfirst($productType ?? 'Producto') }}
                            </span>
                        </div>
                    </div>
                    @if ($monthlyLimit)
                        <div class="rounded-lg border border-dashed border-[var(--retail-stroke)] p-3 text-[11px] text-[var(--retail-text-muted)]">
                            <p class="text-sm font-semibold text-[var(--retail-text)]">Límite mensual</p>
                            <p class="leading-snug">
                                Máximo {{ $monthlyLimit }} unidades.
                                @if ($monthlyRemaining !== null)
                                    Restantes: <span class="font-semibold text-[var(--retail-primary-strong)]">{{ $monthlyRemaining }}</span>
                                @endif
                            </p>
                        </div>
                    @endif
                </div>

                <div>
                    <form wire:submit.prevent="submit"
                        class="space-y-5 rounded-2xl border border-[var(--retail-stroke)] bg-white/95 p-5 shadow-sm">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-semibold text-[var(--retail-text-muted)] uppercase">Tienda</label>
                                <select wire:model.live="storeId"
                                    class="rounded-xl border border-[var(--retail-stroke)] bg-[var(--retail-surface)] px-3 py-2 text-sm text-[var(--retail-text)] focus:border-[var(--retail-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--retail-primary)]/30">
                                    <option value="">Selecciona una tienda</option>
                                    @foreach ($stores as $store)
                                        <option value="{{ $store['id'] }}">{{ $store['label'] ?? ($store['name'] ?? 'Tienda') }}</option>
                                    @endforeach
                                </select>
                                @error('storeId')
                                    <p class="text-xs text-red-500">{{ $message }}</p>
                                @enderror
                                @if (count($stores) === 0)
                                    <p class="text-xs text-[var(--retail-text-muted)]">
                                        Tu usuario no tiene tiendas asociadas. Contacta a soporte para continuar.
                                    </p>
                                @endif
                            </div>

                            <div class="rounded-2xl border border-[var(--retail-primary-strong)] bg-[var(--retail-primary-soft)] p-4 text-right shadow-sm">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.3em] text-[var(--retail-primary-strong)]">Saldo disponible</p>
                                <p class="text-xl font-bold text-[var(--retail-primary-strong)]">
                                    {{ Number::currency($storeBalance, 'COP') }}
                                </p>
                                @if ($selectedStore)
                                    <p class="text-xs text-[var(--retail-primary-strong)]/80">
                                        {{ $selectedStore['label'] ?? ($selectedStore['name'] ?? 'Tienda') }}
                                    </p>
                                @endif
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            @if ($productType === 'recharge')
                                <div class="flex flex-col gap-1">
                                    <label class="text-xs font-semibold uppercase text-[var(--retail-text-muted)]">Valor de la recarga</label>
                                    <div class="relative">
                                        <span class="pointer-events-none absolute left-3 top-2 text-[var(--retail-text-muted)]">$</span>
                                        <input type="number" wire:model.live.debounce.300ms="rechargeAmount" min="1000" step="500"
                                            class="w-full rounded-xl border border-[var(--retail-stroke)] bg-[var(--retail-surface)] px-6 py-2 text-sm focus:border-[var(--retail-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--retail-primary)]/25" />
                                    </div>
                                    <p class="text-xs text-[var(--retail-text-muted)]">
                                        Mínimo $1.000
                                        @if (!empty($product['max_value']))
                                            · Máximo {{ Number::currency($product['max_value'], 'COP') }}
                                        @endif
                                    </p>
                                    @error('rechargeAmount')
                                        <p class="text-xs text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                            @else
                                <div class="flex flex-col gap-1">
                                    <label class="text-xs font-semibold uppercase text-[var(--retail-text-muted)]">Cantidad a redimir</label>
                                    <input type="number" wire:model.live="quantity" min="1"
                                        class="rounded-xl border border-[var(--retail-stroke)] bg-[var(--retail-surface)] px-3 py-2 text-sm focus:border-[var(--retail-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--retail-primary)]/25" />
                                    <p class="text-[11px] text-[var(--retail-text-muted)]">
                                        @if ($product['stock'] !== null)
                                            Stock disponible: {{ $product['stock'] }}
                                        @else
                                            Ingresa la cantidad deseada.
                                        @endif
                                    </p>
                                    @error('quantity')
                                        <p class="text-xs text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endif

                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-semibold uppercase text-[var(--retail-text-muted)]">Notas (opcional)</label>
                                <textarea wire:model.live="notes" rows="3"
                                    class="rounded-xl border border-[var(--retail-stroke)] bg-[var(--retail-surface)] px-3 py-2 text-sm focus:border-[var(--retail-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--retail-primary)]/25"
                                    placeholder="Ej: Entregar junto a la próxima visita."></textarea>
                                @error('notes')
                                    <p class="text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="rounded-2xl border border-[var(--retail-stroke)] bg-[var(--retail-surface)] p-4">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="text-[10px] font-semibold uppercase tracking-[0.3em] text-[var(--retail-text-muted)]">Total estimado</p>
                                    <p class="text-2xl font-bold text-[var(--retail-primary-strong)]">
                                        {{ Number::currency($estimatedTotal, 'COP') }}
                                    </p>
                                </div>
                                <p class="text-xs text-[var(--retail-text-muted)]">
                                    El saldo solo se descuenta cuando la redención sea aprobada y entregada.
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <x-filament::button type="submit" icon="heroicon-o-paper-airplane" color="primary" size="sm"
                                class="bg-[var(--retail-primary)] px-4 py-2 font-semibold text-white hover:bg-[var(--retail-primary-strong)]"
                                :disabled="!$product || !$storeId">
                                Enviar solicitud
                            </x-filament::button>
                            <x-filament::button tag="a" color="gray" size="sm"
                                href="{{ \App\Domain\Retailer\Filament\Pages\StoreCatalogPage::getUrl(panel: 'retailer') }}"
                                class="px-4 py-2">
                                Cancelar
                            </x-filament::button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
