<?php

namespace App\Domain\Store\Jobs;

use App\Domain\Store\Models\Store;
use App\Domain\Store\Services\LiquidationCalculationService;
use App\Domain\Admin\Models\BackgroundProcess;
use App\Domain\User\Models\User;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class LiquidationBatchJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800; // 30 minutos por lote

    public function __construct(
        public array $storeIds,
        public string $period,
        public int $userId,
        public ?int $backgroundProcessId = null
    ) {
    }

    public function handle(LiquidationCalculationService $service): void
    {
        Log::info("Processing LiquidationBatchJob for stores: " . implode(',', $this->storeIds));

        if ($this->batch() && $this->batch()->cancelled()) {
            Log::info("Batch cancelled for stores: " . implode(',', $this->storeIds));
            return;
        }

        $currentUser = User::find($this->userId) ?? User::first();

        $processedCount = 0;
        $failedCount = 0;

        foreach ($this->storeIds as $storeId) {
            $store = Store::find($storeId);
            if (!$store)
                continue;

            try {
                $service->generateLiquidationForStore($store, $this->period, $currentUser);
                $processedCount++;
            } catch (\Throwable $e) {
                $failedCount++;
                Log::error("Error liquidando tienda ID {$storeId}: " . $e->getMessage());
            }
        }

        // Reportar progreso de forma atómica (seguro para concurrencia)
        if ($this->backgroundProcessId && $processedCount > 0) {
            try {
                BackgroundProcess::where('id', $this->backgroundProcessId)
                    ->increment('progress', $processedCount);
            } catch (\Throwable $e) {
                // Si falla actualizar progreso, no detener el proceso
            }
        }
    }
}
