@php
    $conflicts = $getState() ?? [];
@endphp

@if (!empty($conflicts))
    <div class="space-y-3">
        @foreach ($conflicts as $idx => $conflict)
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
                            ICCID: {{ $conflict['iccid'] ?? 'N/A' }} @if (!empty($conflict['idpos'])) | IDPOS:
                            {{ $conflict['idpos'] }} @endif
                        </p>
                        @if (!empty($conflict['existing']) || !empty($conflict['incoming']))
                            <div class="mt-1 grid gap-2 text-xs text-slate-700 sm:grid-cols-2">
                                @if (!empty($conflict['existing']))
                                    <div>
                                        <p class="font-semibold text-slate-800">Actual:</p>
                                        <pre
                                            class="whitespace-pre-wrap text-[11px] text-slate-700">{{ json_encode($conflict['existing'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    </div>
                                @endif
                                @if (!empty($conflict['incoming']))
                                    <div>
                                        <p class="font-semibold text-slate-800">Nuevo:</p>
                                        <pre
                                            class="whitespace-pre-wrap text-[11px] text-slate-700">{{ json_encode($conflict['incoming'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                    <div class="flex flex-col gap-2">
                        @if (($conflict['status'] ?? 'pending') === 'resolved')
                            <div class="text-right">
                                @if (($conflict['resolution'] ?? '') === 'update')
                                    <x-filament::badge color="success">Aplicado</x-filament::badge>
                                @else
                                    <x-filament::badge color="gray">Omitido</x-filament::badge>
                                @endif
                                <div class="mt-1 text-[10px] text-slate-500 leading-tight">
                                    <span class="font-medium text-slate-600">CC:
                                        {{ $conflict['resolved_by_doc'] ?? 'N/A' }}</span><br>
                                    {{ $conflict['resolved_by'] ?? 'User' }}<br>
                                    {{ $conflict['resolved_at'] ?? '' }}
                                </div>
                            </div>
                        @else
                            <x-filament::button color="success" size="sm"
                                wire:click="resolveSalesConflictFromImport({{ $idx }}, 'update')">
                                Aplicar
                            </x-filament::button>
                            <x-filament::button color="secondary" size="sm"
                                wire:click="resolveSalesConflictFromImport({{ $idx }}, 'omit')">
                                Omitir
                            </x-filament::button>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@else
    <p class="text-sm text-slate-600">No hay conflictos para mostrar.</p>
@endif