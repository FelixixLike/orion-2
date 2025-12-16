@php
    use Illuminate\Support\Number;
@endphp

<x-filament-panels::page>
    <div class="space-y-4">
        <x-filament::card>
            <div class="grid md:grid-cols-3 gap-4 items-end">
                <div class="md:col-span-2">
                    <label class="text-xs font-semibold text-gray-600 dark:text-gray-300">Periodo</label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="period">
                            @foreach ($this->getAvailablePeriodsOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
                <div class="flex gap-2 justify-end">
                    <x-filament::button wire:click="refreshReport" color="primary" icon="heroicon-o-arrow-path">
                        Actualizar
                    </x-filament::button>
                    <x-filament::button wire:click="export" color="gray" icon="heroicon-o-arrow-down-tray">
                        Exportar
                    </x-filament::button>
                </div>
            </div>
        </x-filament::card>

        <x-filament::card>
            <div class="grid md:grid-cols-4 gap-4">
                <div>
                    <p class="text-xs text-gray-500">Periodo</p>
                    <p class="text-xl font-semibold">{{ $summary['period'] ?? $period }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Total Claro</p>
                    <p class="text-xl font-semibold">{{ Number::currency($summary['claro_total'] ?? 0, 'COP') }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Total trasladado a tenderos</p>
                    <p class="text-xl font-semibold">{{ Number::currency($summary['tendero_total'] ?? 0, 'COP') }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Diferencia</p>
                    <p
                        class="text-xl font-semibold {{ ($summary['difference'] ?? 0) < 0 ? 'text-red-600' : 'text-emerald-600' }}">
                        {{ Number::currency($summary['difference'] ?? 0, 'COP') }}
                    </p>
                </div>
            </div>
            <div class="mt-4">
                <p class="text-sm text-gray-600">Tiendas liquidadas:
                    <strong>{{ $summary['stores_count'] ?? 0 }}</strong>
                </p>
            </div>
        </x-filament::card>



    </div>
</x-filament-panels::page>