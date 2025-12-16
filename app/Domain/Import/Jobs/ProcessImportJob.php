<?php

namespace App\Domain\Import\Jobs;

use App\Domain\Import\Imports\StoreImport;
use App\Domain\Import\Models\Import;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use App\Domain\Admin\Models\BackgroundProcess;

class ProcessImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hora máximo

    public function __construct(
        protected int $importId,
        protected string $filePath,
        protected int $backgroundProcessId
    ) {
    }

    public function handle(): void
    {
        // Actualizar estado a running
        BackgroundProcess::where('id', $this->backgroundProcessId)->update(['status' => 'running']);

        // Instanciar el importador SIN ShouldQueue
        $importer = new StoreImport($this->importId, false);
        $importer->setBackgroundProcessId($this->backgroundProcessId);

        // Ejecutar importación síncrona (dentro de este Job asíncrono)
        Excel::import($importer, $this->filePath, 'public');
    }
}
