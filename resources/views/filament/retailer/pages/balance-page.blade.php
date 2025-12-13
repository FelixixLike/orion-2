@php
    use Illuminate\Support\Number;

    $activeStore = collect($stores)->firstWhere('id', $activeStoreId);
@endphp

<x-filament-panels::page>
    <div
        class="space-y-6 text-[var(--retail-text)]"
        x-data="{
            search: '',
            type: 'all',
            movements: $wire.entangle('movements'),
            get filteredMovements() {
                return this.movements.filter((movement) => {
                    const matchesType =
                        this.type === 'all'
                        || movement.type === this.type;

                    const text = `${movement.label ?? ''} ${movement.type_label ?? ''} ${movement.status_label ?? ''} ${movement.date ?? ''} ${movement.store_name ?? ''} ${movement.idpos ?? ''}`.toLowerCase();

                    return matchesType && text.includes(this.search.toLowerCase());
                });
            },
        }"
    >
        {{-- Hero --}}
        <div class="rounded-2xl border border-[var(--retail-stroke)] bg-gradient-to-r from-[var(--retail-primary-strong)] via-[var(--retail-primary)] to-[var(--retail-primary-strong)] px-6 py-5 shadow-lg text-white">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div class="space-y-1">
                    <p class="text-xs uppercase tracking-[0.25em]">Panel de tendero</p>
                    <h1 class="text-3xl font-bold">Resumen de saldo y movimientos</h1>
                    <p class="text-sm text-white/80">
                        Aqu&iacute; ves tu saldo actual y los &uacute;ltimos movimientos de liquidaciones y redenciones.
                    </p>
                </div>
                <div class="flex flex-col items-start gap-2 text-sm text-white/80 md:items-end">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-white/15 text-white">
                            <x-filament::icon icon="heroicon-o-user" class="h-5 w-5" />
                        </div>
                        <div class="leading-tight">
                            <p class="font-semibold text-white">
                                {{ auth('retailer')->user()?->name ?? 'Tendero' }}
                            </p>
                            <p class="text-xs text-white/70">Cuenta de tendero</p>
                        </div>
                    </div>
                    @if ($activeStore)
                        <div class="rounded-full border border-white/30 bg-white/10 px-3 py-1 text-xs font-semibold text-white">
                            Tienda activa:
                            {{ ($activeStore['idpos'] ?? 'N/D') . ' - ' . ($activeStore['name'] ?? 'Tienda') }}
                        </div>
                    @endif
                </div>
            </div>
            <div class="mt-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="flex flex-wrap items-center gap-2">
                    @forelse ($stores as $store)
                        <button
                            type="button"
                            wire:click="setActiveStore({{ $store['id'] }})"
                            @class([
                                'rounded-full border px-3 py-1 text-xs font-semibold transition',
                                ($store['id'] ?? null) === $activeStoreId
                                    ? 'border-[var(--retail-primary)] bg-[var(--retail-primary-soft)] text-[var(--retail-primary-strong)]'
                                    : 'border-white/40 bg-white/10 text-white hover:border-white/70',
                            ])
                        >
                            {{ $store['idpos'] ?? 'IDPOS' }} - {{ $store['name'] ?? 'Tienda' }}
                        </button>
                    @empty
                        <span class="text-xs text-white/80">Sin tiendas activas</span>
                    @endforelse
                </div>
                <x-filament::button
                    tag="a"
                    href="{{ \App\Domain\Retailer\Filament\Pages\StoresPage::getNavigationUrl() }}"
                    size="sm"
                    color="gray"
                    icon="heroicon-o-building-storefront"
                    class="bg-white text-[var(--retail-primary-strong)] border-transparent hover:bg-white/90"
                >
                    Gestionar tiendas
                </x-filament::button>
            </div>
        </div>

        @if (! $activeStoreId)
            <x-filament::card class="border-amber-300 bg-amber-50 text-sm text-amber-800">
                <div class="flex flex-col gap-1">
                    <p class="font-semibold">No tienes una tienda activa seleccionada.</p>
                    <p>
                        Elige una desde los chips superiores o activa una tienda en el dashboard para ver tus movimientos.
                    </p>
                </div>
            </x-filament::card>
        @endif

        @if ($activeStoreId)
            {{-- Tarjetas de resumen --}}
            <div class="grid gap-4 md:grid-cols-3">
                <x-filament::card class="bg-[var(--retail-surface)] border-[var(--retail-stroke)]">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-[var(--retail-text-muted)]">
                                Saldo actual
                            </p>
                            <p class="mt-1 text-2xl font-bold text-[var(--retail-text)]">
                                {{ Number::currency($currentBalance ?? 0, 'COP') }}
                            </p>
                        </div>
                    </div>
                </x-filament::card>

                <x-filament::card class="bg-[var(--retail-surface)] border-[var(--retail-stroke)]">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-[var(--retail-text-muted)]">
                                Total cr&eacute;ditos (liquidaciones)
                            </p>
                            <p class="mt-1 text-xl font-semibold text-[var(--retail-success)]">
                                {{ Number::currency($totalCredits ?? 0, 'COP') }}
                            </p>
                        </div>
                    </div>
                </x-filament::card>

                <x-filament::card class="bg-[var(--retail-surface)] border-[var(--retail-stroke)]">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-[var(--retail-text-muted)]">
                                Total d&eacute;bitos (redenciones)
                            </p>
                            <p class="mt-1 text-xl font-semibold text-[var(--retail-danger)]">
                                {{ Number::currency($totalDebits ?? 0, 'COP') }}
                            </p>
                        </div>
                    </div>
                </x-filament::card>
            </div>

            {{-- Filtros --}}
            <div class="flex flex-col gap-3 rounded-2xl border border-[var(--retail-stroke)] bg-[var(--retail-surface)] p-4 shadow-sm md:flex-row md:items-center md:justify-between">
                <div class="relative flex-1">
                    <x-filament::icon
                        icon="heroicon-o-magnifying-glass"
                        class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--retail-text-muted)]"
                    />
                    <input
                        type="text"
                        x-model="search"
                        placeholder="Buscar por tipo, detalle o estado..."
                        class="w-full rounded-full border border-[var(--retail-stroke)] bg-[var(--retail-surface-muted)] px-9 py-2 text-sm text-[var(--retail-text)] placeholder:text-[var(--retail-text-muted)] focus:border-[var(--retail-primary)] focus:outline-none focus:ring-0"
                    />
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="rounded-full border px-3 py-2 text-xs font-semibold uppercase tracking-wide transition"
                        :class="type === 'all'
                            ? 'border-[var(--retail-primary)] bg-[var(--retail-primary-soft)] text-[var(--retail-primary-strong)]'
                            : 'border-[var(--retail-stroke)] bg-[var(--retail-surface-muted)] text-[var(--retail-text-muted)] hover:border-[var(--retail-primary)] hover:text-[var(--retail-text)]'"
                        @click="type = 'all'"
                    >
                        Todo
                    </button>
                    <button
                        type="button"
                        class="rounded-full border px-3 py-2 text-xs font-semibold uppercase tracking-wide transition"
                        :class="type === 'credit'
                            ? 'border-[var(--retail-success)] bg-[var(--retail-primary-soft)] text-[var(--retail-success)]'
                            : 'border-[var(--retail-stroke)] bg-[var(--retail-surface-muted)] text-[var(--retail-text-muted)] hover:border-[var(--retail-primary)] hover:text-[var(--retail-text)]'"
                        @click="type = 'credit'"
                    >
                        Liquidaci&oacute;n
                    </button>
                    <button
                        type="button"
                        class="rounded-full border px-3 py-2 text-xs font-semibold uppercase tracking-wide transition"
                        :class="type === 'debit'
                            ? 'border-[var(--retail-primary)] bg-[var(--retail-primary-soft)] text-[var(--retail-primary-strong)]'
                            : 'border-[var(--retail-stroke)] bg-[var(--retail-surface-muted)] text-[var(--retail-text-muted)] hover:border-[var(--retail-primary)] hover:text-[var(--retail-text)]'"
                        @click="type = 'debit'"
                    >
                        Redenci&oacute;n
                    </button>
                </div>
            </div>

            {{-- Tabla --}}
            <div class="overflow-hidden rounded-2xl border border-[var(--retail-stroke)] bg-[var(--retail-surface)] shadow">
                <template x-if="filteredMovements.length === 0">
                    <div class="p-6 text-center text-sm text-[var(--retail-text-muted)]">
                        No hay movimientos para mostrar. Aseg&uacute;rate de tener una tienda activa o ajusta los filtros.
                    </div>
                </template>

                <div x-show="filteredMovements.length > 0" class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-[var(--retail-stroke)] text-sm text-[var(--retail-text)]">
                        <thead class="bg-[var(--retail-surface-muted)] text-left text-xs font-semibold uppercase tracking-wide text-[var(--retail-text-muted)]">
                            <tr>
                                <th class="px-4 py-3">Fecha</th>
                                <th class="px-4 py-3">Tipo</th>
                                <th class="px-4 py-3">Detalle</th>
                                <th class="px-4 py-3 text-right">Monto</th>
                                <th class="px-4 py-3">Estado</th>
                                <th class="px-4 py-3 w-32">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--retail-stroke)]">
                            <template x-for="movement in filteredMovements" :key="`${movement.type}-${movement.date}-${movement.label}`">
                                <tr class="hover:bg-[var(--retail-primary-soft)]">
                                    <td class="px-4 py-3" x-text="movement.date ?? 'N/D'"></td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                                            :class="(movement.amount ?? 0) >= 0
                                                ? 'bg-[var(--retail-primary-soft)] text-[var(--retail-success)] border border-[var(--retail-stroke)]'
                                                : 'bg-[var(--retail-primary-soft)] text-[var(--retail-primary-strong)] border border-[var(--retail-stroke)]'"
                                            x-text="movement.type_label ?? (movement.type === 'credit' ? 'Liquidacion' : 'Redencion')"
                                        ></span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="text-[var(--retail-text)]" x-text="movement.label ?? 'Detalle'"></span>
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-semibold"
                                        :class="(movement.amount ?? 0) >= 0 ? 'text-[var(--retail-success)]' : 'text-[var(--retail-danger)]'"
                                        x-text="movement.formatted_amount ?? movement.amount"
                                    ></td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="rounded-full px-3 py-1 text-xs font-semibold capitalize border border-[var(--retail-stroke)]"
                                            :class="movement.status_class ?? 'bg-[var(--retail-surface-muted)] text-[var(--retail-text)]'"
                                            x-text="movement.status_label ?? movement.status ?? 'N/D'"
                                        ></span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <a
                                            :href="movement.url ?? '#'"
                                            class="inline-flex items-center gap-1 rounded-full border border-[var(--retail-primary)] px-3 py-1 text-xs font-semibold text-[var(--retail-primary)] hover:bg-[var(--retail-primary-soft)]"
                                            :class="!movement.url ? 'pointer-events-none opacity-40 cursor-default' : ''"
                                        >
                                            <x-filament::icon icon="heroicon-o-eye" class="h-4 w-4" />
                                            Ver
                                        </a>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
	        @endif
	    </div>
	</x-filament-panels::page>
