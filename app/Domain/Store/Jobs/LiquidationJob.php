<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Store\Jobs;

use App\Domain\Store\Services\LiquidationCalculationService;
use App\Domain\Admin\Models\BackgroundProcess;
use App\Domain\User\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Batch;
use Filament\Notifications\Notification;

class LiquidationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600;

    public function __construct(
        protected array $storeIds,
        protected string $period,
        protected int $userId,
        protected int $backgroundProcessId
    ) {
    }

    public function handle(): void
    {
        $process = BackgroundProcess::find($this->backgroundProcessId);

        if (!$process) {
            Log::error("BackgroundProcess {$this->backgroundProcessId} not found");
            return;
        }

        $totalStores = count($this->storeIds);

        $process->update([
            'status' => 'running',
            'name' => "Liquidando periodo {$this->period} ({$totalStores} tiendas) - MODO TURBO",
            'total' => $totalStores,
            'progress' => 0,
        ]);

        // Dividir en lotes de 50 tiendas
        // Para 1000 tiendas -> 20 lotes.
        // Con 8-16 workers, se procesarán 400-800 tiendas casi simultáneamente.
        $chunks = array_chunk($this->storeIds, 50);
        $jobs = [];

        foreach ($chunks as $chunkIds) {
            $jobs[] = new LiquidationBatchJob(
                $chunkIds,
                $this->period,
                $this->userId,
                $this->backgroundProcessId
            );
        }

        $processId = $this->backgroundProcessId;
        $userId = $this->userId;
        $period = $this->period;

        Bus::batch($jobs)
            ->name("Liquidación Masiva {$period}")
            ->allowFailures()
            ->then(function (Batch $batch) use ($processId, $userId, $totalStores, $period) {
                // Éxito: El Batch terminó (pudo tener fallos parciales, pero terminó)
                $proc = BackgroundProcess::find($processId);
                if ($proc) {
                    // Forzar progreso al 100% visualmente
                    $proc->update([
                        'status' => 'completed',
                        'progress' => $totalStores,
                        'error' => $batch->hasFailures() ? 'El proceso terminó con algunos errores en lotes.' : null
                    ]);
                }

                $user = User::find($userId);
                if ($user) {
                    $status = $batch->hasFailures() ? 'warning' : 'success';
                    $msg = $batch->hasFailures()
                        ? "La liquidación de {$period} finalizó, pero algunas tiendas tuvieron problemas. Revisa el log."
                        : "La liquidación de {$period} para {$totalStores} tiendas se completó exitosamente.";

                    Notification::make()
                        ->title($batch->hasFailures() ? 'Proceso completado con alertas' : 'Liquidación Exitosa')
                        ->body($msg)
                        ->status($status)
                        ->sendToDatabase($user);
                }
            })
            ->catch(function (Batch $batch, \Throwable $e) use ($processId, $userId) {
                // Error GRAVE al crear el batch (raro)
                $proc = BackgroundProcess::find($processId);
                $proc?->update(['status' => 'failed', 'error' => $e->getMessage()]);
                Log::error("Error Fatal en Batch Liquidación: " . $e->getMessage());
            })
            ->dispatch();

        Log::info("Liquidación Turbo despachada: " . count($jobs) . " lotes creados.");
    }
}
