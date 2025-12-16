<div class="overflow-x-auto border rounded-lg">
    <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
        <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
            <tr>
                <th scope="col" class="px-6 py-3">Fila</th>
                <th scope="col" class="px-6 py-3">Identificador (ICCID/ID)</th>
                <th scope="col" class="px-6 py-3">Problema</th>
                <th scope="col" class="px-6 py-3">Detalle Técnico</th>
            </tr>
        </thead>
        <tbody>
            @php
                $errors = $record->errors ?? [];
                $details = $errors['details'] ?? [];
                // Limit to 100 to avoid performance issues in UI
                $details = array_slice($details, 0, 100);
            @endphp

            @forelse($details as $error)
                <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                    <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                        {{ $error['row'] ?? 'N/A' }}
                    </td>
                    <td class="px-6 py-4">
                        {{ $error['iccid'] ?? $error['key'] ?? 'N/A' }}
                    </td>
                    <td class="px-6 py-4">
                        @if(isset($error['message']) && (str_contains($error['message'], 'Invalid text representation') || str_contains($error['message'], 'invalid input syntax')))
                            <span
                                class="px-2 py-1 text-xs font-semibold text-yellow-800 bg-yellow-100 rounded-full dark:bg-yellow-200 dark:text-yellow-800">
                                Formato Inválido
                            </span>
                            <div class="mt-1 text-xs">El valor no es un número válido.</div>
                          @elseif(isset($error['message']) && (str_contains($error['message'], 'Unique violation') || str_contains($error['message'], 'duplicate key')))
                            <span
                                class="px-2 py-1 text-xs font-semibold text-red-800 bg-red-100 rounded-full dark:bg-red-200 dark:text-red-900">
                                Duplicado
                            </span>
                            <div class="mt-1 text-xs">Ya existe un registro para esta SIM.</div>
                        @else
                                <span
                                    class="px-2 py-1 text-xs font-semibold text-gray-800 bg-gray-100 rounded-full dark:bg-gray-200 dark:text-gray-900">
                               {{ isset($error['reason']) ? 'Saltado' : 'Error' }}
                                </span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-xs font-mono text-red-600 dark:text-red-400 break-words max-w-sm">
                        {{ Str::limit($error['message'] ?? $error['reason'] ?? 'Sin detalles', 150) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-6 py-4 text-center">
                        @if(isset($errors['summary']['duplicates']) && $errors['summary']['duplicates'] > 0)
                            <div class="p-4 mb-4 text-sm text-yellow-800 rounded-lg bg-yellow-50 dark:bg-gray-800 dark:text-yellow-300"
                                role="alert">
                                <span class="font-medium">Resumen:</span> Se encontraron {{ $errors['summary']['duplicates'] }}
                                registros duplicados que fueron omitidos o contados como fallidos.
                            </div>
                        @else
                            No hay detalles de errores disponibles o solo hay resumen.
                        @endif
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
    @if(isset($errors['details']) && count($errors['details']) > 100)
        <div class="p-2 text-center text-xs text-gray-500">
            Mostrando los primeros 100 errores. Descargue el log para ver más.
        </div>
    @endif
</div>