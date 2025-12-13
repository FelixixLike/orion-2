<?php
/** @var array $movement */
$amount = (float) ($movement['amount'] ?? 0);
$isPositive = $amount >= 0;
$statusLabel = $movement['status_label'] ?? '';
$storeName = $movement['store_name'] ?? 'Tienda';
$idpos = $movement['idpos'] ?? null;
$date = $movement['date'] ?? 'N/D';
$description = $movement['description'] ?? 'Sin detalle';
$formattedAmount = $movement['formatted_amount'] ?? 'COP 0';
$exportUrl = $movement['export_url'] ?? '#';
$exportDisabled = empty($movement['export_url']);
?>

<x-filament-panels::layout>
    <div class="fi-main px-6 py-6 space-y-6 text-[var(--retail-text)]">
        <!-- Encabezado -->
        <div
            class="rounded-2xl border border-[var(--retail-stroke)] bg-gradient-to-r from-[var(--retail-primary-strong)] via-[var(--retail-primary)] to-[var(--retail-primary-strong)] px-6 py-5 shadow-lg text-white"
        >
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div class="space-y-1">
                    <p class="text-xs uppercase tracking-[0.25em]">Panel de tendero</p>
                    <h1 class="text-2xl font-bold">Detalle del movimiento</h1>
                    <p class="text-sm text-white/80">
                        Revisa la información de este movimiento y descarga un comprobante sencillo en PDF.
                    </p>
                </div>
                <div class="flex flex-col items-start gap-2 text-sm text-white/80 md:items-end">
                    <div class="rounded-full border border-white/30 bg-white/10 px-3 py-1 text-xs font-semibold text-white">
                        {{ $storeName }}
                        <?php if (! empty($idpos)) { ?>
                            <span class="text-white/80">(IDPOS-{{ $idpos }})</span>
                        <?php } ?>
                    </div>
                    <p class="text-xs text-white/70">
                        Fecha del movimiento:
                        <span class="font-semibold">{{ $date }}</span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Detalle -->
        <x-filament::card class="space-y-4">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-[var(--retail-text-muted)]">
                        Tipo de movimiento
                    </p>
                    <div class="mt-1 inline-flex items-center gap-2">
                        <span
                            class="rounded-full px-3 py-1 text-xs font-semibold {{ $isPositive ? 'bg-emerald-500/10 text-emerald-700' : 'bg-red-500/10 text-red-700' }}"
                        >
                            {{ $movement['type_label'] ?? 'Movimiento' }}
                        </span>
                        <?php if ($statusLabel !== '') { ?>
                            <span class="rounded-full border border-[var(--retail-stroke)] bg-[var(--retail-surface-muted)] px-3 py-1 text-xs font-semibold">
                                {{ $statusLabel }}
                            </span>
                        <?php } ?>
                    </div>
                </div>

                <div class="text-right">
                    <p class="text-xs font-semibold uppercase tracking-wide text-[var(--retail-text-muted)]">
                        Monto del movimiento
                    </p>
                    <p
                        class="mt-1 text-lg font-semibold {{ $isPositive ? 'text-[var(--retail-success)]' : 'text-[var(--retail-danger)]' }}"
                    >
                        {{ $formattedAmount }}
                    </p>
                </div>
            </div>

            <dl class="grid gap-4 text-sm md:grid-cols-2">
                <div class="space-y-1">
                    <dt class="text-[var(--retail-text-muted)]">Fecha</dt>
                    <dd class="font-medium text-[var(--retail-text)]">
                        {{ $date }}
                    </dd>
                </div>
                <div class="space-y-1">
                    <dt class="text-[var(--retail-text-muted)]">Detalle</dt>
                    <dd class="font-medium text-[var(--retail-text)]">
                        {{ $description }}
                    </dd>
                </div>
                <div class="space-y-1">
                    <dt class="text-[var(--retail-text-muted)]">Tienda</dt>
                    <dd class="font-medium text-[var(--retail-text)]">
                        {{ $storeName }}
                        <?php if (! empty($idpos)) { ?>
                            <span class="text-xs text-[var(--retail-text-muted)]">
                                (IDPOS-{{ $idpos }})
                            </span>
                        <?php } ?>
                    </dd>
                </div>
            </dl>

            <div class="flex flex-col gap-3 border-t border-[var(--retail-stroke)] pt-4 sm:flex-row sm:justify-between">
                <x-filament::button
                    tag="a"
                    size="sm"
                    color="gray"
                    icon="heroicon-o-arrow-left"
                    href="{{ \App\Domain\Retailer\Filament\Pages\BalancePage::getUrl(panel: 'retailer') }}"
                >
                    Volver al balance
                </x-filament::button>

                <x-filament::button
                    tag="a"
                    size="sm"
                    color="primary"
                    icon="heroicon-o-arrow-down-tray"
                    href="{{ $exportUrl }}"
                    :spa-mode="false"
                    :disabled="$exportDisabled"
                >
                    Descargar comprobante (PDF)
                </x-filament::button>
            </div>
        </x-filament::card>
    </div>
</x-filament-panels::layout>
