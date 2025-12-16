<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
declare(strict_types=1);

namespace App\Domain\Import\Jobs;

use App\Domain\Import\Models\Import;
use App\Domain\Import\Services\ImportProcessorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessOperatorReportImportJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $importId
    ) {
    }

    public function handle(): void
    {
        Log::info('ProcessOperatorReportImportJob: Iniciando procesamiento', [
            'import_id' => $this->importId,
        ]);
        $import = Import::find($this->importId);
        ImportProcessorService::process($import);
    }
}

