<?php

declare(strict_types=1);

namespace App\Domain\Import\Imports;

use App\Domain\Import\Exceptions\ImportValidationException;
use App\Domain\Import\Models\Import;
use App\Domain\Import\Models\SalesCondition;
use App\Domain\Import\Services\SimcardService;
use App\Domain\Store\Models\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Row;

class SalesConditionImport implements OnEachRow, WithHeadingRow, SkipsEmptyRows, WithMultipleSheets, WithChunkReading
{
    use Importable;

    public function chunkSize(): int
    {
        return 500; // Procesar 500 filas a la vez para evitar problemas de memoria
    }


    private int $insertedCount = 0;
    private int $updatedCount = 0;
    private int $skippedCount = 0;
    private int $duplicateCount = 0;
    private int $createdSimsCount = 0;
    private int $conflictCount = 0;
    private int $processedRows = 0;

    private array $skippedRows = [];
    private array $duplicateRows = [];
    private array $conflicts = [];
    private bool $headersValidated = false;
    private ?int $createdByUserId = null;

    private array $templateHeaders = [
        'ICCID',
        'NUMERODETELEFONO',
        'IDPOS',
        'VALOR',
        'RESIDUAL',
        'POBLACION',
        'FECHA VENTA',
    ];

    private array $seenKeys = [];
    private array $storeExistenceCache = [];
    private array $idposWarnings = [];

    public function __construct(private readonly int $importId)
    {
    }

    public function sheets(): array
    {
        return [
            'Tabla' => $this,
        ];
    }

    public function onRow(Row $row): void
    {
        $rowData = $this->normalizeRowData($row->toArray());

        if (!$this->headersValidated) {
            $this->validateHeaders($rowData);
            $this->headersValidated = true;
        }

        if (!$this->validateRow($rowData, $row->getIndex())) {
            return;
        }
        $this->processedRows++;

        if ($this->createdByUserId === null) {
            $this->createdByUserId = Import::find($this->importId)?->created_by;
        }

        $iccidRaw = $this->getValue($rowData, 'ICCID');
        $phoneRaw = $this->getValue($rowData, 'NUMERODETELEFONO');
        $idposRaw = $this->getValue($rowData, 'IDPOS');
        $valorRaw = $this->getValue($rowData, 'VALOR');
        $residualRaw = $this->getValue($rowData, 'RESIDUAL');
        $poblacionRaw = $this->getValue($rowData, 'POBLACION');
        $fechaVentaRaw = $this->getValue($rowData, 'FECHA VENTA');

        $iccid = $this->cleanString($iccidRaw);
        $phone = $this->cleanString($phoneRaw);
        $idpos = $this->resolveIdposOrNull($this->cleanString($idposRaw), $row->getIndex());

        if (isset($this->seenKeys[$iccid])) {
            $this->logDuplicateRow($row->getIndex(), $phone, $iccid, 'Duplicado en archivo (ICCID repetido)');
            return;
        }
        $this->seenKeys[$iccid] = true;

        [$salePrice, $saleLabel] = $this->parseSaleValue($valorRaw);

        $residualDecimal = $this->parseResidual($residualRaw);
        $population = $this->cleanString($poblacionRaw);
        $periodDate = $this->parseDate($fechaVentaRaw);
        if ($periodDate === null) {
            $this->logSkippedRow($row->getIndex(), 'invalid_date', ['value' => $fechaVentaRaw]);
            return;
        }
        [$periodYear, $periodMonth] = $this->extractPeriodParts($periodDate);

        DB::transaction(function () use ($row, $iccid, $phone, $idpos, $salePrice, $residualDecimal, $population, $periodDate, $periodYear, $periodMonth) {
            $simcard = SimcardService::findOrCreateByIccid($iccid, $phone);
            if ($simcard->wasRecentlyCreated) {
                $this->createdSimsCount++;
            }

            // ICCID único global
            $existingAny = SalesCondition::where('iccid', $iccid)->first();
            if ($existingAny && ($existingAny->period_year !== $periodYear || $existingAny->period_month !== $periodMonth)) {
                $this->conflictCount++;
                if (count($this->conflicts) < 50) {
                    $this->conflicts[] = [
                        'type' => 'sales_condition_conflict',
                        'row' => $row->getIndex(),
                        'iccid' => $iccid,
                        'idpos' => $idpos,
                        'existing' => [
                            'phone_number' => $existingAny->phone_number,
                            'idpos' => $existingAny->idpos,
                            'sale_price' => $existingAny->sale_price,
                            'commission_percentage' => $existingAny->commission_percentage,
                            'period_date' => $existingAny->period_date?->format('Y-m-d'),
                            'period_year' => $existingAny->period_year,
                            'period_month' => $existingAny->period_month,
                        ],
                        'incoming' => [
                            'phone_number' => $phone,
                            'idpos' => $idpos,
                            'sale_price' => $salePrice,
                            'commission_percentage' => $residualDecimal,
                            'period_date' => $periodDate,
                            'period_year' => $periodYear,
                            'period_month' => $periodMonth,
                        ],
                        'message' => "ICCID {$iccid} ya existe en la base de condiciones. No se permite duplicar ICCID en otro período.",
                        'action' => 'pending',
                    ];
                }
                return;
            }

            // Teléfono único (si viene informado)
            if (!empty($phone)) {
                $existingPhone = SalesCondition::where('phone_number', $phone)->first();
                if ($existingPhone && ($existingPhone->iccid !== $iccid || $existingPhone->period_year !== $periodYear || $existingPhone->period_month !== $periodMonth)) {
                    $this->conflictCount++;
                    if (count($this->conflicts) < 50) {
                        $this->conflicts[] = [
                            'type' => 'sales_condition_conflict',
                            'row' => $row->getIndex(),
                            'iccid' => $iccid,
                            'idpos' => $idpos,
                            'existing' => [
                                'phone_number' => $existingPhone->phone_number,
                                'iccid' => $existingPhone->iccid,
                                'idpos' => $existingPhone->idpos,
                                'sale_price' => $existingPhone->sale_price,
                                'commission_percentage' => $existingPhone->commission_percentage,
                                'period_date' => $existingPhone->period_date?->format('Y-m-d'),
                            ],
                            'incoming' => [
                                'phone_number' => $phone,
                                'iccid' => $iccid,
                                'idpos' => $idpos,
                                'sale_price' => $salePrice,
                                'commission_percentage' => $residualDecimal,
                                'period_date' => $periodDate,
                            ],
                            'message' => "El número {$phone} ya está usado en otra condición. No se permite duplicar teléfono.",
                            'action' => 'pending',
                        ];
                    }
                    return;
                }
            }

            $existing = SalesCondition::where('simcard_id', $simcard->id)
                ->where('period_year', $periodYear)
                ->where('period_month', $periodMonth)
                ->first();

            $incomingData = [
                'iccid' => $iccid,
                'phone_number' => $phone,
                'idpos' => $idpos,
                'sale_price' => $salePrice ?? 0,
                'commission_percentage' => $residualDecimal,
                'period_date' => $periodDate,
                'population' => $population,
                'created_by' => $this->createdByUserId,
                'import_id' => $this->importId,
            ];

            if ($existing) {
                $otherDiffers = false;
                if ($existing->idpos !== $idpos) {
                    $otherDiffers = true;
                }
                if ((float) $existing->commission_percentage !== (float) $residualDecimal) {
                    $otherDiffers = true;
                }
                if ((float) $existing->sale_price !== (float) $salePrice) {
                    $otherDiffers = true;
                }

                $phoneChanged = $existing->phone_number !== $phone;

                if (!$otherDiffers && !$phoneChanged) {
                    return;
                }

                if (!$otherDiffers && $existing->phone_number === null && !empty($phone)) {
                    if ($this->phoneInUseElsewhere($phone, $existing->id)) {
                        $this->conflictCount++;
                        if (count($this->conflicts) < 50) {
                            $this->conflicts[] = [
                                'type' => 'sales_condition_conflict',
                                'row' => $row->getIndex(),
                                'iccid' => $iccid,
                                'idpos' => $idpos,
                                'existing' => [
                                    'phone_number' => $existing->phone_number,
                                    'idpos' => $existing->idpos,
                                    'sale_price' => $existing->sale_price,
                                    'commission_percentage' => $existing->commission_percentage,
                                    'period_date' => $existing->period_date?->format('Y-m-d'),
                                ],
                                'incoming' => [
                                    'phone_number' => $phone,
                                    'idpos' => $idpos,
                                    'sale_price' => $salePrice ?? 0,
                                    'commission_percentage' => $residualDecimal,
                                    'period_date' => $periodDate,
                                ],
                                'message' => "El número {$phone} ya está usado en otra condición. No se permite duplicar teléfono.",
                                'action' => 'pending',
                            ];
                        }
                        return;
                    }

                    $existing->update(['phone_number' => $phone]);
                    $this->updatedCount++;
                    return;
                }

                $this->conflictCount++;
                if (count($this->conflicts) < 50) {
                    $this->conflicts[] = [
                        'type' => 'sales_condition_conflict',
                        'row' => $row->getIndex(),
                        'iccid' => $iccid,
                        'idpos' => $idpos,
                        'existing' => [
                            'phone_number' => $existing->phone_number,
                            'idpos' => $existing->idpos,
                            'sale_price' => $existing->sale_price,
                            'commission_percentage' => $existing->commission_percentage,
                            'period_date' => $existing->period_date?->format('Y-m-d'),
                        ],
                        'incoming' => [
                            'phone_number' => $phone,
                            'idpos' => $idpos,
                            'sale_price' => $salePrice ?? 0,
                            'commission_percentage' => $residualDecimal,
                            'period_date' => $periodDate,
                        ],
                        'message' => "ICCID {$iccid} ya existe para el período {$periodYear}-{$periodMonth} con otros valores (teléfono/residual/venta/idpos).",
                        'action' => 'pending',
                    ];
                }
                return;
            }

            SalesCondition::create(array_merge($incomingData, [
                'simcard_id' => $simcard->id,
                'period_year' => $periodYear,
                'period_month' => $periodMonth,
            ]));
            $this->insertedCount++;
        });
    }

    private function validateRow(array $rowData, int $rowIndex): bool
    {
        $required = ['ICCID', 'NUMERODETELEFONO', 'IDPOS', 'VALOR', 'FECHA VENTA'];
        $missing = [];

        foreach ($required as $field) {
            $val = $this->getValue($rowData, $field);
            if ($val === null || trim((string) $val) === '') {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            $this->logSkippedRow($rowIndex, 'missing_data', ['missing_fields' => implode(', ', $missing)]);
            return false;
        }

        return true;
    }

    private function validateHeaders(array $rowData): void
    {
        $expected = ['iccid', 'numerodetelefono', 'idpos', 'valor', 'residual', 'poblacion', 'fecha_venta'];
        $keys = array_keys($rowData);
        $missing = array_diff($expected, $keys);

        if (!empty($missing)) {
            throw new ImportValidationException('Faltan columnas obligatorias: ' . implode(', ', $missing) . ". Verifique que la hoja 'Tabla' tenga los encabezados exactos.");
        }
    }

    private function normalizeRowData(array $row): array
    {
        return $row;
    }

    private function getValue(array $row, string $originalHeader): ?string
    {
        $slug = (string) \Illuminate\Support\Str::of($originalHeader)->slug('_');
        return isset($row[$slug]) ? (string) $row[$slug] : null;
    }

    private function cleanString(?string $val): ?string
    {
        if ($val === null) {
            return null;
        }
        return trim($val);
    }

    private function parseSaleValue(?string $val): array
    {
        if ($val === null || $val === '') {
            return [null, null];
        }

        $trimmed = trim($val);
        $clean = str_replace(['$', ' ', ','], ['', '', '.'], $trimmed);

        if (is_numeric($clean)) {
            return [(float) $clean, $trimmed];
        }

        // Texto (ej: "regalo", "redimible"): usamos label y numeric null
        return [null, $trimmed];
    }

    private function parseResidual(?string $val): float
    {
        if ($val === null || $val === '') {
            return 0.0;
        }

        $clean = trim(str_replace(['%', ' ', ','], ['', '', '.'], $val));

        if ($clean === '' || !is_numeric($clean)) {
            return 0.0;
        }

        $num = (float) $clean;

        if ($num <= 0) {
            return 0.0;
        }

        // Si viene como 0.07 (formato decimal), convertirlo a porcentaje 7
        // Si ya viene como 7, mantenerlo igual.
        return $num <= 1 ? $num * 100 : $num;
    }

    private function parseDate(?string $val): ?string
    {
        if (!$val) {
            return null;
        }
        try {
            if (is_numeric($val)) {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($val)->format('Y-m-d');
            }

            $raw = trim((string) $val);

            foreach (['d/m/Y', 'd-m-Y', 'd/m/y', 'd-m-y'] as $format) {
                try {
                    $date = Carbon::createFromFormat($format, $raw);
                    if ($date !== false) {
                        return $date->format('Y-m-d');
                    }
                } catch (\Throwable $e) {
                    // Try next format.
                }
            }

            return Carbon::parse(str_replace('/', '-', $raw))->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function extractPeriodParts(string $date): array
    {
        $d = Carbon::parse($date);
        return [(int) $d->format('Y'), (int) $d->format('m')];
    }

    private function logSkippedRow(int $rowIndex, string $reason, array $context = []): void
    {
        $this->skippedCount++;
        if (count($this->skippedRows) < 50) {
            $this->skippedRows[] = array_merge(['row' => $rowIndex, 'reason' => $reason], $context);
        }
    }

    private function logDuplicateRow(int $rowIndex, ?string $phone, ?string $iccid, string $msg): void
    {
        $this->duplicateCount++;
        if (count($this->duplicateRows) < 50) {
            $this->duplicateRows[] = ['row' => $rowIndex, 'iccid' => $iccid, 'phone' => $phone, 'message' => $msg];
        }
    }

    private function phoneInUseElsewhere(string $phone, int $ignoreId): bool
    {
        return SalesCondition::where('phone_number', $phone)
            ->where('id', '!=', $ignoreId)
            ->exists();
    }

    public function getStats(): array
    {
        return [
            'total_processed' => $this->processedRows,
            'inserted' => $this->insertedCount,
            'updated' => $this->updatedCount,
            'skipped' => $this->skippedCount,
            'duplicates' => $this->duplicateCount,
            'created_sims' => $this->createdSimsCount,
            'conflicts' => $this->conflictCount,
        ];
    }

    public function getErrors(): array
    {
        $errors = [
            'summary' => [
                'inserted' => $this->insertedCount,
                'updated' => $this->updatedCount,
                'skipped' => $this->skippedCount,
                'duplicates' => $this->duplicateCount,
                'total_processed' => $this->processedRows,
                'conflicts' => $this->conflictCount,
            ],
        ];
        if (!empty($this->skippedRows)) {
            $errors['skipped_rows'] = $this->skippedRows;
        }
        if (!empty($this->duplicateRows)) {
            $errors['duplicates'] = $this->duplicateRows;
        }
        if (!empty($this->conflicts)) {
            $errors['conflicts'] = $this->conflicts;
            $errors['summary']['conflicts'] = $this->conflictCount;
        }
        if (!empty($this->idposWarnings)) {
            $errors['idpos_warnings'] = $this->idposWarnings;
        }
        return $errors;
    }

    private function resolveIdposOrNull(?string $idpos, int $rowIndex): ?string
    {
        if ($idpos === null || $idpos === '') {
            return null;
        }

        if (!isset($this->storeExistenceCache[$idpos])) {
            $this->storeExistenceCache[$idpos] = Store::where('idpos', $idpos)->exists();
        }

        if (!$this->storeExistenceCache[$idpos]) {
            if (count($this->idposWarnings) < 50) {
                $this->idposWarnings[] = [
                    'row' => $rowIndex,
                    'idpos' => $idpos,
                    'message' => "IDPOS {$idpos} no existe actualmente en tiendas. Se guardará para futura vinculación.",
                ];
            }

            return $idpos;
        }

        return $idpos;
    }
}
