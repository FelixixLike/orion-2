@php
    $storeLabel = $storeLabel ?? null;
    $tender = $tender ?? null;
    $storeId = $condition?->idpos ? \App\Domain\Store\Models\Store::where('idpos', $condition->idpos)->value('id') : null;
@endphp

<div class="space-y-3 text-sm text-slate-700 dark:text-slate-200">
    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900/60">
        <p class="text-xs font-semibold text-slate-500">Tienda</p>
        <p class="font-semibold">{{ $storeLabel ?? 'Sin tienda asociada' }}</p>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900/60">
        <p class="text-xs font-semibold text-slate-500">Tendero</p>
        @if ($tender)
            <p class="font-semibold">{{ $tender['name'] ?? 'Tendero' }}</p>
            <p class="text-xs text-slate-500">{{ $tender['email'] ?? '' }}</p>
        @else
            <p class="text-sm">No hay tendero asociado.</p>
        @endif
    </div>
    <div class="flex flex-wrap gap-2">
        <x-filament::badge color="gray">IDPOS: {{ $condition->idpos ?? 'N/D' }}</x-filament::badge>
        <x-filament::badge color="gray">ICCID: {{ $condition->iccid ?? 'N/D' }}</x-filament::badge>
    </div>

</div>