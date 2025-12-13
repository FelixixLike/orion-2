<?php

declare(strict_types=1);

namespace App\Domain\Import\Jobs;

use App\Domain\Import\Models\Import;
use App\Domain\Import\Services\ImportProcessorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessSalesConditionImportJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $importId
    ) {
    }

    public function handle(): void
    {
        $import = Import::find($this->importId);

        if (!$import) {
            return;
        }

        ImportProcessorService::process($import);
    }
}

