<?php

namespace App\Domain\Import\Imports;

use App\Domain\Import\Models\Import;
use App\Domain\Import\Models\SalesCondition;
use App\Domain\Import\Services\SimcardService;
use App\Domain\Store\Models\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Row;

use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterImport;
use App\Domain\Import\Services\ImportProcessorService;

class SalesConditionImport implements OnEachRow, WithHeadingRow, SkipsEmptyRows, WithMultipleSheets, WithChunkReading, ShouldQueue, WithEvents
{
    use Importable;

    private $importId;

    // Contadores explícitos por Chunk
    private int $chunkProcessedCount = 0;
    private int $chunkSuccessCount = 0;
    private int $chunkErrorCount = 0;
    private array $chunkErrors = [];

    public function __construct(int $importId)
    {
        $this->importId = $importId;
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
                $totalRows = 0;
                $allSheets = $event->getReader()->getTotalRows();
                if (isset($allSheets['Tabla'])) {
                    $totalRows = max(0, $allSheets['Tabla'] - 1);
                } elseif (isset($allSheets['tabla'])) {
                    $totalRows = max(0, $allSheets['tabla'] - 1);
                } else {
                    $totalRows = max(0, reset($allSheets) - 1);
                }

                Import::where('id', $this->importId)->update([
                    'total_rows' => $totalRows,
                    'status' => 'processing',
                ]);
            },
            \Maatwebsite\Excel\Events\AfterChunk::class => function (\Maatwebsite\Excel\Events\AfterChunk $event) {
                // SUMAR (Incrementar) lo procesado en este chunk a la DB
                if ($this->chunkProcessedCount > 0) {
                    Import::where('id', $this->importId)->increment('processed_rows', $this->chunkProcessedCount);
                }
                // Exitosos se calculan: procesados - fallidos
                if ($this->chunkErrorCount > 0) {
                    Import::where('id', $this->importId)->increment('failed_rows', $this->chunkErrorCount);
                }

                // Guardar Errores (Hacer merge con lo existente si se puede, o al menos guardar los nuevos)
                if (!empty($this->chunkErrors)) {
                    $import = Import::find($this->importId);
                    $currentErrors = $import->errors ?? [];

                    // Fusionar errores nuevos
                    if (isset($currentErrors['summary']['duplicates'])) {
                        $currentErrors['summary']['duplicates'] += ($this->chunkErrors['summary']['duplicates'] ?? 0);
                    } else {
                        $currentErrors['summary']['duplicates'] = ($this->chunkErrors['summary']['duplicates'] ?? 0);
                    }

                    // Agregar lista detallada (solo si son pocos, para no llenar la DB)
                    // Si ya hay muchos errores, solo guardamos el resumen para proteger la DB
                    if (!isset($currentErrors['details']))
                        $currentErrors['details'] = [];
                    if (isset($this->chunkErrors['details'])) {
                        $currentErrors['details'] = array_merge($currentErrors['details'], $this->chunkErrors['details']);
                    }

                    $import->update(['errors' => $currentErrors]);
                }
            },
            AfterImport::class => function (AfterImport $event) {
                ImportProcessorService::finalize($this->importId);
            },
        ];
    }

    public function onRow(Row $row)
    {
        $rowIndex = $row->getIndex();
        $rowArray = $row->toArray();

        $this->chunkProcessedCount++;

        $iccid = $rowArray['iccid'] ?? null;

        // Validación básica de campos
        if (!$iccid) {
            $this->chunkErrorCount++;
            return;
        }

        $idpos = $rowArray['idpos'] ?? null;
        $valor = $rowArray['valor'] ?? null;
        $residual = $rowArray['residual'] ?? null;
        // La columna RESIDUAL % a veces llega como integer o float, asegurar validación

        if (!$idpos || is_null($valor) || is_null($residual)) {
            $this->chunkErrorCount++;
            $this->addError($rowIndex, $iccid, 'Faltan campos obligatorios (idpos, valor, residual)');
            return;
        }

        try {
            // Conversión de fecha Excel
            $periodDate = null;
            if (isset($rowArray['fecha_venta'])) {
                if (is_numeric($rowArray['fecha_venta'])) {
                    $periodDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($rowArray['fecha_venta']);
                } else {
                    try {
                        $periodDate = Carbon::parse($rowArray['fecha_venta']);
                    } catch (\Exception $e) {
                        // Fallo silencioso de fecha
                    }
                }
            }

            if (!$periodDate) {
                $this->chunkErrorCount++;
                $this->addError($rowIndex, $iccid, 'Fecha inválida');
                return;
            }

            $period = Carbon::instance($periodDate)->startOfMonth();
            $periodYear = (int) $period->format('Y');
            $periodMonth = (int) $period->format('m');

            // Find SIM
            $simcard = SimcardService::findOrCreateByIccid((string) $iccid, $rowArray['numerodetelefono'] ?? null);

            if (!$simcard) {
                $this->chunkErrorCount++;
                $this->addError($rowIndex, $iccid, 'No se pudo procesar SIM');
                return;
            }

            // Check Duplicate
            $existing = SalesCondition::where('simcard_id', $simcard->id)
                ->where('period_year', $periodYear)
                ->where('period_month', $periodMonth)
                ->exists();

            if ($existing) {
                $this->chunkErrorCount++;
                $this->addDuplicateError();
                $this->addError($rowIndex, $iccid, "Duplicado en periodo $periodYear-$periodMonth", 'duplicate');
            } else {
                // Wrap creation in transaction to prevent main transaction abort on race condition error
                DB::transaction(function () use ($simcard, $iccid, $rowArray, $periodDate, $periodYear, $periodMonth) {
                    // Double check lock optional but simple creation attempt is enough with transaction handling exception logic implicitly if needed
                    // But here we rely on the parent transaction rollback of Laravel if this fails
                    SalesCondition::create([
                        'simcard_id' => $simcard->id,
                        'iccid' => (string) $iccid,
                        'phone_number' => $rowArray['numerodetelefono'] ?? null,
                        'idpos' => $rowArray['idpos'],
                        'sale_price' => $this->sanitizePrice($rowArray['valor']),
                        'commission_percentage' => $this->sanitizePercentage($rowArray['residual']),
                        'population' => $rowArray['poblacion'] ?? null,
                        'period_date' => $periodDate->format('Y-m-d'),
                        'period_year' => $periodYear,
                        'period_month' => $periodMonth,
                        'created_by' => 1,
                    ]);
                });
                $this->chunkSuccessCount++;
            }

        } catch (\Throwable $e) {
            $this->chunkErrorCount++;
            $this->addError($rowIndex, $iccid, 'Excepción: ' . $e->getMessage());
        }
    }

    private function addError($row, $iccid, $msg, $type = 'error')
    {
        $this->chunkErrors['details'][] = [
            'row' => $row,
            'iccid' => $iccid,
            'message' => $msg,
            'type' => $type
        ];
    }

    private function addDuplicateError()
    {
        if (!isset($this->chunkErrors['summary']['duplicates'])) {
            $this->chunkErrors['summary']['duplicates'] = 0;
        }
        $this->chunkErrors['summary']['duplicates']++;
    }

    public function chunkSize(): int
    {
        return 500;
    }

    private function sanitizePrice($value): float
    {
        if (is_null($value))
            return 0.0;
        if (is_numeric($value))
            return (float) $value;

        // Remove non-numeric chars except dot and comma
        $cleaned = preg_replace('/[^0-9.,]/', '', (string) $value);
        // Replace comma with dot if present
        $cleaned = str_replace(',', '.', $cleaned);

        return (float) $cleaned;
    }

    private function sanitizePercentage($value): float
    {
        if (is_null($value))
            return 0.0;

        // If it's a string like "7%" or "RESIDUAL 3%"
        $stringVal = (string) $value;

        // Extract first numeric sequence found
        if (preg_match('/(\d+([.,]\d+)?)/', $stringVal, $matches)) {
            $number = str_replace(',', '.', $matches[1]);
            return (float) $number;
        }

        // Default fallback if no number found (e.g. "RESIDUAL" without number)
        // Check if user wants 0 or error. Assuming 0 for safety.
        return 0.0;
    }
}
