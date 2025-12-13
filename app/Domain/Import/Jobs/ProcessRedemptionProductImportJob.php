<?php

declare(strict_types=1);

namespace App\Domain\Import\Jobs;

use App\Domain\Import\Models\Import;
use App\Domain\Import\Services\ImportProcessorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessRedemptionProductImportJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $importId
    ) {}

    public function handle(): void
    {
        Log::info('ProcessRedemptionProductImportJob: inicia procesamiento', [
            'import_id' => $this->importId,
        ]);

        $import = Import::find($this->importId);

        if (! $import) {
            Log::warning('ProcessRedemptionProductImportJob: import no encontrado', [
                'import_id' => $this->importId,
            ]);
            return;
        }

        ImportProcessorService::process($import);
    }
}
