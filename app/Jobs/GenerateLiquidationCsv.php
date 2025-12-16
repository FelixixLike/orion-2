<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Jobs;

use App\Domain\User\Models\User;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class GenerateLiquidationCsv implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutos max

    public function __construct(
        protected int $userId,
        protected string $period,
        protected ?string $search = null
    ) {
    }

    public function handle(): void
    {
        // 1. Obtener datos (Extremadamente rápido desde RAM/Redis)
        $key = 'preview_data_' . $this->userId . '_' . $this->period;
        $data = Cache::get($key);

        if (!$data) {
            // Si falló la caché, intentamos notificar error y abortar
            Log::error("GenerateLiquidationCsv: No data found in cache for key $key");
            return;
        }

        $allStores = collect($data);

        // 2. Filtrado (en memoria, rápido)
        if ($this->search) {
            $search = strtolower($this->search);
            $allStores = $allStores->filter(
                fn($s) =>
                str_contains(strtolower($s['name']), $search) ||
                str_contains((string) $s['idpos'], $search)
            );
        }

        // 3. Preparar archivo (Native PHP stream)
        // Usamos storage_path directo para máxima velocidad de I/O
        $fileName = 'liquidacion-detalle-' . $this->period . '-' . time() . '.csv';
        // Asegurar que el directorio existe
        if (!file_exists(storage_path('app/public/exports'))) {
            mkdir(storage_path('app/public/exports'), 0755, true);
        }

        $path = storage_path('app/public/exports/' . $fileName);
        $file = fopen($path, 'w');

        // BOM para Excel (UTF-8)
        fwrite($file, "\xEF\xBB\xBF");

        // Cabeceras
        fputcsv($file, ['CÓDIGO TIENDA', 'NOMBRE TIENDA', 'ICCID', 'TELÉFONO', '% COMISIÓN', 'RECARGA MOVILCO', 'BASE LIQUIDACIÓN', 'TOTAL A PAGAR']);

        // 4. Bucle de escritura optimizado
        foreach ($allStores as $store) {
            if (empty($store['lines']))
                continue;

            foreach ($store['lines'] as $line) {
                // fputcsv es C-level code, muy eficiente
                fputcsv($file, [
                    $store['idpos'],
                    $store['name'],
                    $line['iccid'] ?? 'N/D',
                    $line['phone_number'] ?? 'N/D',
                    number_format($line['residual_percentage'], 2) . '%',
                    $line['movilco_recharge_amount'],
                    $line['base_liquidation_final'],
                    $line['pago_residual'],
                ]);
            }
        }

        fclose($file);

        // 5. Notificar
        $user = User::find($this->userId);
        if ($user) {
            Notification::make()
                ->title('Reporte Ultrarrápido Listo')
                ->body('La exportación se ha completado exitosamente. <br><br> <a href="' . asset('storage/exports/' . $fileName) . '" target="_blank" style="font-weight:bold; color:#f59e0b;">Descargar Archivo</a>')
                ->success()
                ->sendToDatabase($user);
        }
    }
}
