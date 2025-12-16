<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Store\Jobs;

use App\Domain\Store\Services\LiquidationCalculationService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class CalculateLiquidationBatchJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    public function __construct(
        public string $period,
        public array $reportIds,
        public string $baseResultKey,
        public int $chunkIndex,
        public string $progressKey,
        public int $progressStep
    ) {
    }

    public function handle(LiquidationCalculationService $service): void
    {
        if ($this->batch() && $this->batch()->cancelled()) {
            return;
        }

        $result = $service->calculateForPeriod($this->period, null, $this->reportIds);

        Cache::put($this->baseResultKey . '_' . $this->chunkIndex, $result, 1200);

        try {
            Cache::increment($this->progressKey, $this->progressStep);
        } catch (\Throwable $e) {
        }
    }
}
