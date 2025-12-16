<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Console\Commands;

use App\Domain\Import\Models\OperatorReport;
use App\Domain\Import\Services\IccidCleanerService;
use Illuminate\Console\Command;

class CleanIccidsInRawPayload extends Command
{
    protected $signature = 'iccid:clean-raw-payload';

    protected $description = 'Limpia los ICCIDs en el raw_payload de todos los OperatorReports (quita primeros 2 y último dígito)';

    public function handle()
    {
        $this->info('Iniciando limpieza de ICCIDs en raw_payload...');

        $reports = OperatorReport::whereNotNull('raw_payload')->get();
        $totalReports = $reports->count();
        $updated = 0;
        $skipped = 0;

        $this->info("Se encontraron {$totalReports} registros con raw_payload.");

        $bar = $this->output->createProgressBar($totalReports);
        $bar->start();

        foreach ($reports as $report) {
            $rawPayload = $report->raw_payload;

            if (isset($rawPayload['iccid']) && !empty($rawPayload['iccid'])) {
                $iccidRaw = (string) $rawPayload['iccid'];

                // Solo limpiar si el ICCID tiene 20 dígitos (formato completo)
                if (strlen($iccidRaw) === 20) {
                    $iccidCleaned = IccidCleanerService::clean($iccidRaw);

                    if ($iccidCleaned && $iccidCleaned !== $iccidRaw) {
                        $rawPayload['iccid'] = $iccidCleaned;
                        $report->raw_payload = $rawPayload;
                        $report->save();
                        $updated++;
                    } else {
                        $skipped++;
                    }
                } else {
                    // Ya está limpio o tiene otro formato
                    $skipped++;
                }
            } else {
                $skipped++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ Proceso completado!");
        $this->info("📊 Registros actualizados: {$updated}");
        $this->info("⏭️  Registros omitidos: {$skipped}");

        return Command::SUCCESS;
    }
}
