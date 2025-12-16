<div wire:poll.3s="loadProcesses" class="fixed top-20 right-4 z-[9999] w-96 max-w-full space-y-2 pointer-events-none"
    style="position: fixed; top: 80px; right: 20px; z-index: 9999; width: 350px;">
    @foreach($processes as $process)
        {{-- Usamos x-data para manejar la visibilidad localmente e instantáneamente --}}
        <div x-data="{ show: true }" x-show="show" x-transition:leave="transition ease-in duration-300"
            x-transition:leave-start="opacity-100 transform scale-100" x-transition:leave-end="opacity-0 transform scale-95"
            wire:key="process-{{ $process['id'] }}"
            class="bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden"
            style="pointer-events: auto;">

            <div class="p-4">
                <div class="flex items-start justify-between mb-2">
                    <div class="flex-1">
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-white">
                            {{ $process['name'] }}
                        </h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            @if($process['status'] === 'running')
                                En progreso...
                            @elseif($process['status'] === 'completed')
                                ✓ Completado
                            @elseif($process['status'] === 'failed')
                                ✗ Error
                            @else
                                Iniciando...
                            @endif
                        </p>
                    </div>

                    {{-- Botón de cerrar con AlpineJS para feedback instantáneo --}}
                    <button type="button" @click="show = false; $wire.dismiss({{ $process['id'] }})"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 ml-2 cursor-pointer focus:outline-none">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                            </path>
                        </svg>
                    </button>
                </div>

                @if($process['total'] > 0)
                    <div class="space-y-1">
                        <div class="flex justify-between text-xs text-gray-600 dark:text-gray-400">
                            <span>{{ number_format($process['progress']) }} / {{ number_format($process['total']) }}</span>
                            <span>{{ round(($process['total'] > 0 ? $process['progress'] / $process['total'] : 0) * 100) }}%</span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            <div class="h-2 rounded-full transition-all duration-300 
                                                    @if($process['status'] === 'completed') bg-green-500
                                                    @elseif($process['status'] === 'failed') bg-red-500
                                                    @else bg-blue-500
                                                    @endif"
                                style="width: {{ min(100, round(($process['total'] > 0 ? $process['progress'] / $process['total'] : 0) * 100)) }}%">
                            </div>
                        </div>
                    </div>
                @else
                    <div class="flex items-center space-x-2">
                        <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-500"></div>
                        <span class="text-xs text-gray-500">Calculando...</span>
                    </div>
                @endif

                @if($process['error'])
                    <p class="mt-2 text-xs text-red-600 dark:text-red-400 break-words">
                        {{ $process['error'] }}
                    </p>
                @endif
            </div>
        </div>
    @endforeach
</div>