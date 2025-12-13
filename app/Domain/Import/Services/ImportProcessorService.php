<?php

declare(strict_types=1);

namespace App\Domain\Import\Services;

use App\Domain\Import\Enums\ImportStatus;
use App\Domain\Import\Enums\ImportType;
use App\Domain\Import\Events\ImportStatusChanged;
use App\Domain\Import\Exceptions\ImportValidationException;
use App\Domain\Import\Factories\ImportFactory;
use App\Domain\Import\Models\Import;
use App\Domain\Import\Services\ImportTypeDetectorService;
use App\Domain\Import\Services\OperatorReportConsolidatorService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ImportProcessorService
{
    /**
     * Procesa una importación según su tipo.
     */
    public static function process(Import $import): void
    {
        if ($import->status !== ImportStatus::PENDING->value) {
            Log::warning('ImportProcessorService: Import no está en estado pending', [
                'import_id' => $import->id,
                'status' => $import->status,
            ]);
            return;
        }

        $import->update(['status' => ImportStatus::PROCESSING->value]);
        event(new ImportStatusChanged($import->fresh()));

        try {
            $filePath = Storage::disk('local')->path($import->file);

            if (!file_exists($filePath)) {
                throw new \Exception("Archivo no encontrado: {$import->file} (buscado en: {$filePath})");
            }

            // Detectar tipo automáticamente si no está definido
            if (empty($import->type) || $import->type === 'unknown') {
                $detectedType = ImportTypeDetectorService::detect($filePath);

                if (!$detectedType) {
                    throw new \Exception('No se pudo detectar automáticamente el tipo de archivo');
                }

                $import->update(['type' => $detectedType]);
                $import->refresh();
            }

            // Validar periodo/corte y duplicados por tipo

            // Validar periodo/corte y duplicados por tipo
            if ($import->type === ImportType::OPERATOR_REPORT->value) {
                // Aunque el usuario ingresó el periodo, podemos verificarlo o usarlo.
                // Si por alguna razón llegara vacío (no debería por el required del form), intentamos derivarlo.
                if (empty($import->period)) {
                    $derivedPeriod = self::derivePeriodFromOperatorReport($filePath);
                    $import->update(['period' => $derivedPeriod]);
                }

                // Forzamos corte 0 para consolidado mensual
                $import->update(['cutoff_number' => 0]);
                $import->refresh();
            } elseif (
                empty($import->period) &&
                in_array($import->type, [ImportType::RECHARGE->value])
            ) {
                // Para Recargas y otros que lo requieran, SI es obligatorio
                throw new ImportValidationException('El campo Periodo (YYYY-MM) es obligatorio para este tipo de archivo.');
            }

            if ($import->type === ImportType::OPERATOR_REPORT->value) {
                $cutoff = 0;
                // Validación eliminada para permitir cargas parciales
                // if (
                //     self::importAlreadyProcessed($import, $import->period, $cutoff, [
                //         ImportStatus::COMPLETED->value,
                //     ])
                // ) {
                //     throw new ImportValidationException("Ya existe un import del operador para el período {$import->period}.");
                // }
            } elseif ($import->type === ImportType::RECHARGE->value) {
                if (empty($import->period)) {
                    throw new ImportValidationException('Debe especificar el periodo (YYYY-MM) para las recargas.');
                }
                if (self::importAlreadyProcessed($import, $import->period, 0)) {
                    // Permitir cargas parciales también para recargas
                    // throw new ImportValidationException("Ya existe un import de recargas para el período {$import->period}.");
                }
            } elseif ($import->type === ImportType::SALES_CONDITION->value) {
                // Si el usuario envía periodo, verificamos duplicidad. Si no, permitimos sin periodo.
                if (!empty($import->period) && self::importAlreadyProcessed($import, $import->period, 0)) {
                    throw new ImportValidationException("Ya existe un import de condiciones para el período {$import->period}.");
                }
            }

            $totalRows = self::countRows($filePath);
            $import->update(['total_rows' => $totalRows]);

            $importImporter = ImportFactory::create($import->type, $import->id);

            DB::beginTransaction();

            try {
                // LIMPIEZA DE SEGURIDAD: Borrar intentos previos de este mismo import_id
                self::cleanPreviousImportData($import);

                Excel::import($importImporter, $filePath);

                // LIMPIEZA DE DUPLICADOS: Si por alguna razón quedaron duplicados internos, los borramos
                self::removeDuplicatesInImport($import);

                // Consolidar cortes del operador a nivel mensual (se mantienen registros de cortes individuales).
                if ($import->type === ImportType::OPERATOR_REPORT->value) {
                    (new OperatorReportConsolidatorService())->consolidate($import);
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

            $updateData = [
                'status' => ImportStatus::COMPLETED->value,
                'processed_rows' => $totalRows,
                'ignored_duplicates' => 0,
            ];

            if (method_exists($importImporter, 'getStats')) {
                $stats = $importImporter->getStats();
                $updateData['processed_rows'] = $stats['total_processed'] ?? $totalRows;
                $updateData['failed_rows'] = ($stats['skipped'] ?? 0) + ($stats['duplicates'] ?? 0);
                $updateData['ignored_duplicates'] = $stats['duplicates'] ?? 0;
            }

            if (method_exists($importImporter, 'getErrors')) {
                $importerErrors = $importImporter->getErrors();
                if (!empty($importerErrors)) {
                    $updateData['errors'] = $importerErrors;
                }
            }

            $import->update($updateData);
            event(new ImportStatusChanged($import->fresh()));
        } catch (ImportValidationException $e) {
            Log::warning('Validation error during import', [
                'import_id' => $import->id,
                'user_message' => $e->getUserMessage(),
                'technical_details' => $e->getTechnicalDetails(),
            ]);

            $import->update([
                'status' => ImportStatus::FAILED->value,
                // Guardamos el mensaje legible para mostrarlo en Filament.
                'errors' => array_merge(
                    $e->toArray(),
                    ['message' => $e->getUserMessage()]
                ),
            ]);
            event(new ImportStatusChanged($import->fresh()));
        } catch (\Exception $e) {
            $reference = uniqid('IMP-', true);

            Log::error('Error procesando importación', [
                'import_id' => $import->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'reference' => $reference,
            ]);

            $import->update([
                'status' => ImportStatus::FAILED->value,
                'errors' => [
                    'type' => 'system_error',
                    'message' => "Error de servidor al procesar el archivo. Código {$reference}. Intenta de nuevo o revisa el log.",
                    'technical_details' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
            ]);
            event(new ImportStatusChanged($import->fresh()));
        }
    }

    private static function countRows(string $filePath): int
    {
        try {
            /** @var array<int, array<int, mixed>> $reader */
            $reader = Excel::toArray(new \stdClass(), $filePath);

            if (empty($reader[0])) {
                return 0;
            }

            $nonEmptyRows = 0;
            $isFirstRow = true;

            foreach ($reader[0] as $row) {
                if ($isFirstRow) {
                    $isFirstRow = false;
                    continue;
                }

                $hasData = false;
                foreach ($row as $cell) {
                    if ($cell !== null && $cell !== '') {
                        $hasData = true;
                        break;
                    }
                }

                if ($hasData) {
                    $nonEmptyRows++;
                }
            }

            return $nonEmptyRows;
        } catch (\Exception $e) {
            Log::warning('Error contando filas del archivo', [
                'file' => $filePath,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    private static function importAlreadyProcessed(Import $import, string $period, int $cutoffNumber, ?array $statuses = null): bool
    {
        $query = Import::where('type', $import->type)
            ->where('period', $period)
            ->where('cutoff_number', $cutoffNumber)
            ->where('id', '!=', $import->id);

        if ($statuses === null) {
            $query->where('status', '!=', ImportStatus::FAILED->value);
        } else {
            $query->whereIn('status', $statuses);
        }

        return $query->exists();
    }

    /**
     * Obtiene el periodo (YYYY-MM) a partir de la columna FECHADECORTE.
     *
     * @throws ImportValidationException
     */
    private static function derivePeriodFromOperatorReport(string $filePath): string
    {
        /** @var array<int, array<int, array<int, mixed>>> $sheets */
        $sheets = Excel::toArray(new \stdClass(), $filePath);
        if (empty($sheets)) {
            throw new ImportValidationException('El archivo está vacío o no tiene datos para leer el periodo.');
        }

        $targetRows = null;
        $cutoffIndex = null;
        $headerRowIndex = 0;
        $scannedHeaders = [];

        foreach ($sheets as $rows) {
            if (empty($rows)) {
                continue;
            }

            $maxRowsToScan = min(count($rows), 25);
            foreach ($rows as $i => $row) {
                if ($i >= $maxRowsToScan) {
                    break;
                }
                foreach ($row as $idx => $headerCell) {
                    $headerValue = (string) $headerCell;
                    if ($headerValue !== '') {
                        $scannedHeaders[] = $headerValue;
                    }
                    if (self::isCutoffHeader($headerValue)) {
                        $cutoffIndex = $idx;
                        $headerRowIndex = $i;
                        $targetRows = $rows;
                        break 3;
                    }
                }
            }
        }

        if ($cutoffIndex === null) {
            $available = array_slice(array_unique(array_filter($scannedHeaders)), 0, 15);
            $msg = "No se encontró la columna FECHADECORTE para derivar el periodo.\n";
            if (!empty($available)) {
                $msg .= 'Encabezados detectados: ' . implode(', ', $available);
            }
            throw new ImportValidationException($msg);
        }

        if ($targetRows === null) {
            throw new ImportValidationException('No se pudo leer la hoja de datos (Tabla) para derivar el periodo.');
        }

        $periods = [];
        foreach ($targetRows as $i => $row) {
            // Saltar fila de encabezados
            if ($i <= $headerRowIndex) {
                continue;
            }
            $cell = $row[$cutoffIndex] ?? null;
            if ($cell === null || $cell === '') {
                continue;
            }

            $date = null;
            if (is_numeric($cell)) {
                $date = Carbon::instance(ExcelDate::excelToDateTimeObject((float) $cell));
            } else {
                try {
                    $date = Carbon::parse((string) $cell);
                } catch (\Exception $e) {
                    // continuar para validar otros valores
                    continue;
                }
            }

            if ($date) {
                $periods[$date->format('Y-m')] = true;
            }
        }

        if (empty($periods)) {
            throw new ImportValidationException('No se pudo obtener el periodo desde FECHADECORTE. Verifica que haya fechas válidas.');
        }

        if (count($periods) > 1) {
            $list = implode(', ', array_keys($periods));
            throw new ImportValidationException("Todas las filas deben pertenecer al mismo mes. Encontrados: {$list}");
        }

        return array_key_first($periods);
    }

    private static function normalizeHeaderName(string $header): string
    {
        $normalized = strtolower(trim($header));
        $normalized = str_replace([' ', '-', '.', "\t"], '_', $normalized);
        return preg_replace('/_+/', '_', $normalized) ?? '';
    }

    private static function isCutoffHeader(string $header): bool
    {
        if ($header === '') {
            return false;
        }

        $normalized = self::normalizeHeaderName($header);
        $lettersOnly = preg_replace('/[^a-z]/', '', strtolower($header)) ?? '';

        $exactMatches = [
            'fechadecorte',
            'fecha_de_corte',
            'fecha_corte',
            'fecha_corte_',
            'fecha_de_corte_',
        ];

        if (in_array($normalized, $exactMatches, true) || in_array($lettersOnly, ['fechadecorte', 'fechadecort'], true)) {
            return true;
        }

        if ($lettersOnly) {
            $distance = levenshtein($lettersOnly, 'fechadecorte');
            if ($distance <= 2) {
                return true;
            }
        }

        if (str_contains($lettersOnly, 'fechadecort') || str_contains($lettersOnly, 'fechacorte')) {
            return true;
        }

        if (str_starts_with($lettersOnly, 'fechadec')) {
            return true;
        }

        return false;
    }

    private static function cleanPreviousImportData(Import $import): void
    {
        try {
            match ($import->type) {
                ImportType::OPERATOR_REPORT->value => \App\Domain\Import\Models\OperatorReport::where('import_id', $import->id)->delete(),
                ImportType::RECHARGE->value => \App\Domain\Import\Models\Recharge::where('import_id', $import->id)->delete(),
                default => null,
            };
        } catch (\Exception $e) {
            Log::warning('Error limpando datos previos del import', [
                'import_id' => $import->id,
                'error' => $e->getMessage()
            ]);
        }
    }
    private static function removeDuplicatesInImport(Import $import): void
    {
        // Solo aplica para Reporte de Operador donde la duplicidad es crítica por sumas
        if ($import->type === ImportType::OPERATOR_REPORT->value) {
            DB::statement("DELETE FROM operator_reports a USING operator_reports b WHERE a.id < b.id AND a.import_id = ? AND a.phone_number = b.phone_number", [$import->id]);
        }
    }
}
