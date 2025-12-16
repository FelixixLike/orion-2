<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Store\Jobs;

use App\Domain\Import\Models\OperatorReport;
use App\Domain\Store\Services\LiquidationCalculationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;

class CalculateLiquidationPreviewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    public function __construct(
        public string $period,
        public ?int $userId,
        public string $progressCacheKey,
        public string $resultCacheKey
    ) {
    }

    public function handle(): void
    {
        Log::info("Starting PARALLEL calculation for period {$this->period}");

        [$year, $month] = explode('-', $this->period);
        $period = $this->period;

        // 1. Obtener IDs de reportes a procesar
        $ids = OperatorReport::query()
            ->where('is_consolidated', true)
            ->where(function ($q) use ($year, $month, $period) {
                $q->where(function ($sub) use ($year, $month) {
                    $sub->where('period_year', $year)->where('period_month', $month);
                })
                    ->orWhere('period_label', $period)
                    ->orWhere(function ($sub) use ($year, $month) {
                        $sub->whereNull('period_year')
                            ->whereYear('cutoff_date', $year)
                            ->whereMonth('cutoff_date', $month);
                    });
            })
            ->whereNull('liquidation_item_id')
            ->pluck('id')
            ->toArray();

        if (empty($ids)) {
            FinalizeLiquidationPreviewJob::dispatch($this->period, 'empty', 0, $this->resultCacheKey, $this->progressCacheKey);
            return;
        }

        // 2. Dividir en Chunks (Lotes)
        // 2000 items por lote. Si tienes 16 nucleos, y son 32000 items, son 16 lotes -> 1 por nucleo.
        $chunks = array_chunk($ids, 2000);
        $totalChunks = count($chunks);

        // El 90% del progreso es el calculo, 10% es el finalizador
        $progressStep = (int) max(1, floor(90 / $totalChunks));

        $baseKey = 'liq_batch_' . uniqid();

        $jobs = [];
        foreach ($chunks as $index => $chunkIds) {
            $jobs[] = new CalculateLiquidationBatchJob(
                $this->period,
                $chunkIds,
                $baseKey,
                $index,
                $this->progressCacheKey,
                $progressStep
            );
        }

        Cache::put($this->progressCacheKey, 5, 3600); // 5% Inicio

        $resultKey = $this->resultCacheKey;
        $progressKey = $this->progressCacheKey;

        // 3. Despachar Lote Paralelo
        Bus::batch($jobs)
            ->then(function (Batch $batch) use ($period, $baseKey, $totalChunks, $resultKey, $progressKey) {
                FinalizeLiquidationPreviewJob::dispatch($period, $baseKey, $totalChunks, $resultKey, $progressKey);
            })
            ->catch(function (Batch $batch, \Throwable $e) use ($progressKey) {
                Log::error("Error in liquidation batch: " . $e->getMessage());
                Cache::put($progressKey, -1, 300); // Error flag?
            })
            ->dispatch();

        Log::info("Dispatched {$totalChunks} batches for liquidation preview.");
    }
}
