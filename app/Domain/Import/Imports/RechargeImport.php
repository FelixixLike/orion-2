<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
declare(strict_types=1);

namespace App\Domain\Import\Imports;

use App\Domain\Import\Constants\ExcelColumnNames;
use App\Domain\Import\Enums\ImportType;
use App\Domain\Import\Exceptions\ImportColumnMappingException;
use App\Domain\Import\Exceptions\ImportValidationException;
use App\Domain\Import\Models\Import;
use App\Domain\Import\Models\Recharge;
use App\Domain\Import\Services\ColumnTranslator;
use App\Domain\Import\Services\SimcardService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Row;

use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\AfterChunk;
use App\Domain\Import\Services\ImportProcessorService;

class RechargeImport implements OnEachRow, WithHeadingRow, SkipsEmptyRows, WithMultipleSheets, WithChunkReading, ShouldQueue, WithEvents
{
    use Importable;

    // Contadores por Chunk
    private int $chunkProcessedCount = 0;
    private int $chunkErrorCount = 0; // Incluye duplicados y fallos
    private array $chunkErrors = [];

    // Validaciones
    private bool $headersValidated = false;
    private array $templateHeaders = [
        'ICCID',
        'NUMERO',
        'VALOR RECARGA',
        'MES',
    ];
    private array $seenKeys = [];

    private ?int $periodYear = null;
    private ?int $periodMonth = null;
    private ?string $periodLabel = null;
    private ?int $createdBy = null;
    private ?int $backgroundProcessId = null;

    public function __construct(private readonly int $importId)
    {
        $import = Import::find($importId);
        if ($import) {
            $this->createdBy = $import->created_by;
            if ($import->period) {
                try {
                    $periodDate = Carbon::createFromFormat('Y-m', $import->period);
                    $this->periodYear = (int) $periodDate->format('Y');
                    $this->periodMonth = (int) $periodDate->format('m');
                    $this->periodLabel = $periodDate->format('Y-m');
                } catch (\Throwable $e) {
                    // Fallback se maneja en onRow
                }
            }
        }
    }

    public function setBackgroundProcessId(int $id): void
    {
        $this->backgroundProcessId = $id;
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function sheets(): array
    {
        return [
            'Tabla' => $this,
        ];
    }

    public function registerEvents(): array
    {
        return [
            \Maatwebsite\Excel\Events\BeforeImport::class => function (\Maatwebsite\Excel\Events\BeforeImport $event) {
                // Cálculo estricto de fila 'Total'
                $totalRows = 0;
                $allSheets = $event->getReader()->getTotalRows();
                if (isset($allSheets['Tabla'])) {
                    $totalRows = max(0, $allSheets['Tabla'] - 1);
                } elseif (isset($allSheets['tabla'])) {
                    $totalRows = max(0, $allSheets['tabla'] - 1);
                } else {
                    $totalRows = max(0, reset($allSheets) - 1);
                }

                if ($this->importId) {
                    Import::where('id', $this->importId)->update([
                        'total_rows' => $totalRows,
                        'status' => 'processing',
                    ]);
                }

                if ($this->backgroundProcessId) {
                    \App\Domain\Admin\Models\BackgroundProcess::where('id', $this->backgroundProcessId)
                        ->update(['status' => 'running', 'total' => $totalRows]);
                }
            },

            AfterChunk::class => function (AfterChunk $event) {
                // Actualización masiva de DB
                if ($this->importId) {
                    if ($this->chunkProcessedCount > 0) {
                        Import::where('id', $this->importId)->increment('processed_rows', $this->chunkProcessedCount);
                    }
                    if ($this->chunkErrorCount > 0) {
                        Import::where('id', $this->importId)->increment('failed_rows', $this->chunkErrorCount);
                    }

                    // Guardado de Errores y Duplicados
                    if (!empty($this->chunkErrors)) {
                        $import = Import::find($this->importId);
                        $currentErrors = $import->errors ?? [];

                        // Mezclar Resumen (Saltados / Duplicados)
                        if (isset($this->chunkErrors['summary'])) {
                            foreach ($this->chunkErrors['summary'] as $key => $val) {
                                $currentErrors['summary'][$key] = ($currentErrors['summary'][$key] ?? 0) + $val;
                            }
                        }

                        // Agregar Detalles (limitado para no reventar DB)
                        if (isset($this->chunkErrors['details'])) {
                            if (!isset($currentErrors['details']))
                                $currentErrors['details'] = [];
                            // Solo agregamos si no son miles
                            if (count($currentErrors['details']) < 500) {
                                $currentErrors['details'] = array_merge($currentErrors['details'], $this->chunkErrors['details']);
                            }
                        }

                        $import->update(['errors' => $currentErrors]);
                    }
                }

                if ($this->backgroundProcessId && $this->chunkProcessedCount > 0) {
                    \App\Domain\Admin\Models\BackgroundProcess::where('id', $this->backgroundProcessId)
                        ->increment('progress', $this->chunkProcessedCount);
                }

                // Reset Chunk Counters
                $this->chunkProcessedCount = 0;
                $this->chunkErrorCount = 0;
                $this->chunkErrors = [];
                // seenKeys se mantiene para evitar duplicados en el mismo archivo
            },

            AfterImport::class => function (AfterImport $event) {
                ImportProcessorService::finalize($this->importId);
                if ($this->backgroundProcessId) {
                    \App\Domain\Admin\Models\BackgroundProcess::where('id', $this->backgroundProcessId)
                        ->update(['status' => 'completed']);
                }
            },
        ];
    }

    public function onRow(Row $row): void
    {
        $this->chunkProcessedCount++;
        $rowData = $this->normalizeRowData($row->toArray());
        $rowIndex = $row->getIndex();

        try {
            if (!$this->headersValidated) {
                $this->validateHeaders($rowData);
                $this->headersValidated = true;
            }

            if (!$this->validateRow($rowData, $rowIndex)) {
                $this->chunkErrorCount++;
                return;
            }

            $phoneNumber = $this->extractPhoneNumber($rowData);
            $iccid = $this->extractIccid($rowData);

            if (!$phoneNumber) {
                $this->logSkippedRow($rowIndex, 'missing_phone_number');
                return;
            }

            $simcard = $iccid ? SimcardService::findByIccid($iccid) : SimcardService::findByPhoneNumber($phoneNumber);

            if (!$simcard) {
                // Increment specific counter for clarity in UI
                if (!isset($this->chunkErrors['summary']['sim_not_found'])) {
                    $this->chunkErrors['summary']['sim_not_found'] = 0;
                }
                $this->chunkErrors['summary']['sim_not_found']++;

                $this->logSkippedRow($rowIndex, 'Simcard no encontrada en Base de Datos (Condiciones SIM)', ['phone' => $phoneNumber]);
                return;
            }

            $rechargeAmount = $this->extractRechargeAmount($rowData);
            $periodDate = $this->extractPeriodDate($rowData);

            // Duplicate check removed as per user request: multiple recharges can exist with same details
            // $dedupeKey = implode('|', [$iccid ?: 'NO_ICCID', $phoneNumber, $periodDate, $rechargeAmount]);
            // if (isset($this->seenKeys[$dedupeKey])) ...

            Recharge::create([
                'simcard_id' => $simcard->id,
                'iccid' => $iccid ?: $simcard->iccid,
                'phone_number' => $phoneNumber,
                'recharge_amount' => $rechargeAmount,
                'period_date' => $periodDate,
                'period_year' => $this->resolvePeriodYear($periodDate),
                'period_month' => $this->resolvePeriodMonth($periodDate),
                'period_label' => $this->resolvePeriodLabel($periodDate),
                'import_id' => $this->importId,
                'created_by' => $this->createdBy,
            ]);

            // Éxito se infiere al no incrementar chunkErrorCount

        } catch (\Throwable $e) {
            $this->logSkippedRow($rowIndex, 'exception', ['message' => $e->getMessage()]);
        }
    }

    // --- Helpers de Reporte y Validación ---

    private function logSkippedRow(int $row, string $reason, array $data = []): void
    {
        $this->chunkErrorCount++;
        if (!isset($this->chunkErrors['summary']['skipped']))
            $this->chunkErrors['summary']['skipped'] = 0;
        $this->chunkErrors['summary']['skipped']++;

        $this->chunkErrors['details'][] = [
            'row' => $row,
            'type' => 'skipped',
            'reason' => $reason,
            'info' => $data
        ];
    }

    private function logDuplicateRow(int $row, $phone, $date, $amount): void
    {
        $this->chunkErrorCount++;
        if (!isset($this->chunkErrors['summary']['duplicates']))
            $this->chunkErrors['summary']['duplicates'] = 0;
        $this->chunkErrors['summary']['duplicates']++;

        $this->chunkErrors['details'][] = [
            'row' => $row,
            'type' => 'duplicate',
            'message' => "Duplicado en archivo: $phone - $date",
        ];
    }

    private function validateRow(array $rowData, int $rowIndex): bool
    {
        $required = ['phone_number', 'recharge_amount'];
        $missing = [];

        foreach ($required as $field) {
            $val = $this->getValueByKeys($rowData, ExcelColumnNames::RECHARGE[$field]);
            if ($val === null || trim($val) === '')
                $missing[] = $field;
        }

        if (!empty($missing)) {
            $this->logSkippedRow($rowIndex, 'missing_required_data', ['fields' => implode(', ', $missing)]);
            return false;
        }
        return true;
    }

    // --- Helpers de Extracción ---

    private function validateHeaders(array $rowData): void
    {
        // Lógica simplificada: si no explota, asumimos OK, o validaciones extra si se desean
    }

    private function normalizeRowData(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            $normalizedKey = strtolower(trim((string) $key));
            $normalizedKey = str_replace([' ', '-', '.', "\t"], '_', $normalizedKey);
            $normalized[$normalizedKey] = $value;
        }
        return $normalized;
    }

    private function extractPhoneNumber(array $rowData): ?string
    {
        return $this->getValueByKeys($rowData, ExcelColumnNames::RECHARGE['phone_number']);
    }

    private function extractRechargeAmount(array $rowData): ?float
    {
        $val = $this->getValueByKeys($rowData, ExcelColumnNames::RECHARGE['recharge_amount']);
        return $val ? (float) str_replace(',', '.', $val) : 0.0;
    }

    private function extractIccid(array $rowData): ?string
    {
        $val = $this->getValueByKeys($rowData, ExcelColumnNames::RECHARGE['iccid'] ?? ['iccid']);
        return $val ? trim((string) $val) : null;
    }

    private function extractPeriodDate(array $rowData): ?string
    {
        $val = $this->getValueByKeys($rowData, ExcelColumnNames::RECHARGE['period_date']);
        if (!$val)
            return Carbon::now()->format('Y-m-d');

        try {
            return is_numeric($val)
                ? \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($val)->format('Y-m-d')
                : Carbon::parse($val)->format('Y-m-d');
        } catch (\Exception $e) {
            return Carbon::now()->format('Y-m-d');
        }
    }

    private function getValueByKeys(array $rowData, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($rowData[$key]) && $rowData[$key] !== '')
                return (string) $rowData[$key];
        }
        return null;
    }

    // Period Resolvers
    // Period Resolvers
    private function resolvePeriodYear($date)
    {
        // Prioritize the period set on the Import (User selection)
        if ($this->periodYear) {
            return $this->periodYear;
        }
        return $date ? Carbon::parse($date)->year : null;
    }

    private function resolvePeriodMonth($date)
    {
        if ($this->periodMonth) {
            return $this->periodMonth;
        }
        return $date ? Carbon::parse($date)->month : null;
    }

    private function resolvePeriodLabel($date)
    {
        if ($this->periodLabel) {
            return $this->periodLabel;
        }
        return $date ? Carbon::parse($date)->format('Y-m') : null;
    }
}
