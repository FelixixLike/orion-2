<?php

declare(strict_types=1);

namespace App\Domain\Import\Imports;

use App\Domain\Import\Builders\OperatorReportBuilder;
use App\Domain\Import\Enums\ImportType;
use App\Domain\Import\Exceptions\ImportColumnMappingException;
use App\Domain\Import\Exceptions\ImportValidationException;
use App\Domain\Import\Services\ColumnTranslator;
use App\Domain\Import\Services\DataNormalizerService;
use App\Domain\Import\Services\IccidCleanerService;
use App\Domain\Import\Services\RowDataExtractor;
use App\Domain\Import\Support\OperatorReportSchema;
use App\Domain\Import\Models\Simcard;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use App\Domain\Import\Models\Import;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithFormatData;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Row;

class OperatorReportImport implements OnEachRow, WithHeadingRow, SkipsEmptyRows, WithFormatData, WithMultipleSheets, WithChunkReading
{
    use Importable;

    private int $skippedCount = 0;
    private array $skippedRows = [];
    private int $insertedCount = 0;
    private int $duplicateCount = 0;
    private array $duplicateRows = [];
    private bool $headersValidated = false;
    private array $seenKeys = [];

    /**
     * Define el tamaño del bloque de lectura para optimizar memoria.
     */
    public function chunkSize(): int
    {
        return 1000;
    }

    /**
     * Encabezados exactos de la hoja "Tabla" en Pagos_Claro.xlsx.
     */
    private array $templateHeaders = OperatorReportSchema::HEADERS;
    private array $requiredColumns = [
        'phone_number',
        'city_code',
        'coid',
        'commission_status',
        'activation_date',
        'cutoff_date',
        'commission_paid_80',
        'commission_paid_20',
        'recharge_amount',
        'recharge_period',
        'custcode',
        'total_recharge_per_period'
    ];
    private ?int $periodYear = null;
    private ?int $periodMonth = null;
    private ?int $createdBy = null;

    public function __construct(
        private readonly int $importId
    ) {
        $import = Import::find($importId);
        if ($import) {
            $this->createdBy = $import->created_by;
            if ($import->period) {
                $periodDate = Carbon::createFromFormat('Y-m', $import->period);
                $this->periodYear = (int) $periodDate->format('Y');
                $this->periodMonth = (int) $periodDate->format('m');
            }
        }
    }

    public function sheets(): array
    {
        return [
            'Tabla' => $this,
        ];
    }

    public function onRow(Row $row): void
    {
        try {
            if (!$this->headersValidated) {
                $this->validateHeaders($row);
                $this->headersValidated = true;
            }

            $rawOriginalRow = $row->toArray();
            $normalizedData = DataNormalizerService::normalizeKeys($rawOriginalRow);
            $extractor = RowDataExtractor::forOperatorReport($normalizedData);

            $missingFields = [];
            $requiredFields = [
                'phone_number',
                'city_code',
                'coid',
                'commission_status',
                'activation_date',
                'cutoff_date',
                'commission_paid_80',
                'commission_paid_20',
                'recharge_amount',
                'recharge_period',
                'custcode',
                'total_recharge_per_period'
            ];

            foreach ($requiredFields as $field) {
                $val = $extractor->getString($field);
                if ($val === null || trim($val) === '') {
                    $missingFields[] = $field;
                }
            }

            if (!empty($missingFields)) {
                $translatedMissing = ColumnTranslator::translateMultiple($missingFields, ImportType::OPERATOR_REPORT->value);
                $this->logSkippedRow($row->getIndex(), 'missing_data', [
                    'missing_fields' => implode(', ', $translatedMissing)
                ]);
                return;
            }

            $phoneNumber = $extractor->getPhoneNumber('phone_number');

            Log::debug('OperatorReportImport: Antes de buscar simcard', [
                'import_id' => $this->importId,
                'row_number' => $row->getIndex(),
                'phone_number' => $phoneNumber,
                'raw_data' => $normalizedData,
            ]);

            $simcard = $this->findExistingSimcard($extractor, $phoneNumber);

            Log::debug('OperatorReportImport: Después de buscar simcard', [
                'import_id' => $this->importId,
                'row_number' => $row->getIndex(),
                'simcard_id' => $simcard?->id,
            ]);

            $this->createOperatorReport($extractor, $simcard, $phoneNumber, $row->getIndex(), $rawOriginalRow);

            $this->logProcessedRow($row->getIndex(), $simcard?->id);

        } catch (\Throwable $e) {
            Log::error('OperatorReportImport: ERROR REAL en fila (ANTES de 25P02)', [
                'import_id' => $this->importId,
                'row_number' => $row->getIndex(),
                'row_data' => $row->toArray(),
                'normalized_data' => $normalizedData ?? null,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    private function validateHeaders(Row $row): void
    {
        $rawRow = $row->toArray();
        $this->validateTemplateHeaders($rawRow);

        $normalizedData = DataNormalizerService::normalizeKeys($rawRow);
        $availableColumns = array_keys($normalizedData);
        $missingColumns = [];

        foreach ($this->requiredColumns as $requiredColumn) {
            if (!in_array($requiredColumn, $availableColumns)) {
                $missingColumns[] = $requiredColumn;
            }
        }

        if (!empty($missingColumns)) {
            $translations = ColumnTranslator::getTranslations(ImportType::OPERATOR_REPORT->value);

            throw new ImportColumnMappingException(
                $missingColumns,
                $availableColumns,
                $translations
            );
        }
    }

    /**
     * Valida que los encabezados coincidan con la plantilla Pagos_Claro.xlsx (hoja Tabla).
     *
     * @param array<string, mixed> $rowData
     * @throws ImportValidationException
     */
    private function validateTemplateHeaders(array $rowData): void
    {
        $expectedNormalized = array_map([$this, 'normalizeHeaderName'], $this->templateHeaders);
        $expectedMap = array_combine($expectedNormalized, $this->templateHeaders);
        $availableOriginal = array_keys($rowData);
        $availableNormalized = array_map([$this, 'normalizeHeaderName'], $availableOriginal);
        $availableMap = array_combine($availableNormalized, $availableOriginal);

        $missing = array_values(array_diff($expectedNormalized, $availableNormalized));
        $unknown = array_values(array_diff($availableNormalized, $expectedNormalized));

        if (!empty($missing) || !empty($unknown)) {
            $missingColumns = array_map(fn(string $key) => $expectedMap[$key] ?? $key, $missing);
            $unknownColumns = array_map(fn(string $key) => $availableMap[$key] ?? $key, $unknown);
            $message = "El archivo no coincide con la plantilla.\n";
            $message .= 'Faltan columnas: ' . $this->formatList($missingColumns) . ".\n";
            $message .= 'Columnas desconocidas: ' . $this->formatList($unknownColumns) . '.';

            throw new ImportValidationException($message);
        }
    }

    private function normalizeHeaderName(string $header): string
    {
        return OperatorReportSchema::normalizeHeader($header);
    }

    private function formatList(array $items): string
    {
        if (empty($items)) {
            return 'ninguna';
        }

        return implode(', ', $items);
    }

    private function findExistingSimcard(RowDataExtractor $extractor, string $phoneNumber): ?Simcard
    {
        $normalizedData = $extractor->getRawData();
        $iccidRaw = $normalizedData['iccid'] ?? null;

        if ($iccidRaw !== null && $iccidRaw !== '') {
            if (is_float($iccidRaw)) {
                $iccidString = number_format($iccidRaw, 0, '', '');
            } else {
                $iccidString = (string) $iccidRaw;
            }

            $iccidCleaned = IccidCleanerService::clean($iccidString);

            if ($iccidCleaned) {
                return Simcard::where('iccid', $iccidCleaned)->first();
            }
        }

        if ($phoneNumber !== null && $phoneNumber !== '') {
            return Simcard::where('phone_number', $phoneNumber)->first();
        }

        return null;
    }

    private function createOperatorReport(
        RowDataExtractor $extractor,
        $simcard,
        string $phoneNumber,
        int $rowIndex,
        array $rawOriginalRow
    ): void {
        $rawData = $extractor->getRawData();
        $activationDate = $extractor->getDate('activation_date');
        $cutoffDate = $extractor->getDate('cutoff_date');

        if (!$activationDate || !$cutoffDate) {
            $this->logSkippedRow($rowIndex, 'invalid_date', [
                'raw_activation_date' => $rawData['activation_date'] ?? null,
                'raw_cutoff_date' => $rawData['cutoff_date'] ?? null,
            ]);
            return;
        }

        $paymentPercentageRaw = $extractor->getDecimal('payment_percentage');
        if ($paymentPercentageRaw !== null) {
            $val = (float) $paymentPercentageRaw;
            // Si viene 18, lo volvemos 0.18 para guardar en DB (decimal)
            // Si viene 0.18, se queda 0.18
            if ($val > 1) {
                $paymentPercentage = $val / 100;
            } else {
                $paymentPercentage = $val;
            }
        }

        $commission80 = $extractor->getDecimal('commission_paid_80');
        $commission20 = $extractor->getDecimal('commission_paid_20');
        $totalCommission = ($commission80 ?? 0) + ($commission20 ?? 0);
        $operatorAmount = $extractor->getDecimal('total_recharge_per_period');
        $montoCarga = $extractor->getDecimal('recharge_amount') ?? 0;

        // Ya lo tenemos en decimal (0.18), usamos directo
        $paymentRate = (float) ($paymentPercentage ?? 0);

        $calculated = $montoCarga * $paymentRate;
        $paidTotal = (float) ($commission80 ?? 0) + (float) ($commission20 ?? 0);
        $diff = $paidTotal - $calculated;

        Log::info('OperatorReportImport: Datos extraídos', [
            'import_id' => $this->importId,
            'row_number' => $rowIndex,
            'raw_activation_date' => $rawData['activation_date'] ?? null,
            'raw_cutoff_date' => $rawData['cutoff_date'] ?? null,
            'raw_payment_percentage' => $rawData['payment_percentage'] ?? 'NOT_FOUND',
            'payment_percentage_decimal' => $paymentPercentageRaw,
            'payment_percentage_normalized' => $paymentPercentage,
            'parsed_activation_date' => $activationDate,
            'parsed_cutoff_date' => $cutoffDate,
            'all_keys' => array_keys($rawData),
        ]);

        Log::info('OperatorReportImport: ANTES DE INSERTAR', [
            'import_id' => $this->importId,
            'row_number' => $rowIndex,
            'simcard_id' => $simcard?->id,
            'city_code' => $extractor->getString('city_code'),
            'coid' => $extractor->getString('coid'),
            'commission_status' => $extractor->getString('commission_status'),
            'activation_date' => $activationDate?->format('Y-m-d'),
            'cutoff_date' => $cutoffDate?->format('Y-m-d'),
            'commission_paid_80' => $commission80,
            'commission_paid_20' => $commission20,
            'total_commission' => $totalCommission,
            'recharge_amount' => $extractor->getDecimal('recharge_amount'),
            'recharge_period' => $extractor->getString('recharge_period'),
            'payment_percentage' => $paymentPercentage,
            'custcode' => $extractor->getString('custcode'),
            'total_recharge_per_period' => $operatorAmount,
        ]);

        $builder = (new OperatorReportBuilder());

        if ($simcard) {
            $builder->forSimcard($simcard);
        }

        $rawPayload = OperatorReportSchema::normalizeRow($rawOriginalRow);

        // Extraer y limpiar ICCID para guardar en la nueva columna
        $iccidRaw = $extractor->getString('iccid');
        $iccidCleaned = null;

        Log::info('OperatorReportImport: ICCID Processing', [
            'row' => $rowIndex,
            'iccid_raw' => $iccidRaw,
            'iccid_raw_length' => $iccidRaw ? strlen($iccidRaw) : 0,
            'raw_payload_iccid_before' => $rawPayload['iccid'] ?? 'NOT_SET',
        ]);

        if ($iccidRaw) {
            $iccidCleaned = IccidCleanerService::clean($iccidRaw);

            Log::info('OperatorReportImport: ICCID Cleaned', [
                'row' => $rowIndex,
                'iccid_cleaned' => $iccidCleaned,
                'iccid_cleaned_length' => $iccidCleaned ? strlen($iccidCleaned) : 0,
            ]);

            // CRÍTICO: Sobrescribir el dato en el payload crudo para que la visualización (que usa raw_payload) muestre el dato limpio.
            if ($iccidCleaned) {
                $rawPayload['iccid'] = $iccidCleaned;

                Log::info('OperatorReportImport: ICCID Overwritten in Payload', [
                    'row' => $rowIndex,
                    'payload_iccid_after' => $rawPayload['iccid'] ?? 'NOT_SET',
                    'all_payload_keys' => array_keys($rawPayload),
                ]);
            }
        }

        $builder
            ->withIccid($iccidCleaned) // Nuevo campo limpio
            ->withPhoneNumber($phoneNumber)
            ->withCityCode($extractor->getString('city_code'))
            ->withCoid($extractor->getString('coid'))
            ->withCommissionStatus($extractor->getString('commission_status'))
            ->withActivationDate($activationDate)
            ->withCutoffDate($cutoffDate)
            ->withCommissionPaid80($commission80)
            ->withCommissionPaid20($commission20)
            ->withTotalCommission($totalCommission)
            ->withRechargeAmount($extractor->getDecimal('recharge_amount'))
            ->withRechargePeriod($extractor->getString('recharge_period'))
            ->withPaymentPercentage($paymentPercentage)
            ->withCustcode($extractor->getString('custcode'))
            ->withTotalRechargePerPeriod($operatorAmount)
            ->withPeriod($this->periodYear, $this->periodMonth)
            ->withTotals($paidTotal, $calculated, $diff)
            ->withRawPayload($rawPayload)
            ->withCreatedBy($this->createdBy)
            ->forImport($this->importId)
            ->build();
    }

    public function getStats(): array
    {
        return [
            'inserted' => $this->insertedCount,
            'skipped' => $this->skippedCount,
            'duplicates' => $this->duplicateCount,
            'total_processed' => $this->insertedCount + $this->skippedCount + $this->duplicateCount,
        ];
    }

    public function getErrors(): array
    {
        $errors = [];

        $errors['summary'] = $this->getStats();

        if ($this->skippedCount > 0) {
            $errors['skipped'] = [
                'message' => "Se saltaron {$this->skippedCount} fila(s) por datos inválidos.",
                'count' => $this->skippedCount,
                'rows' => $this->skippedRows,
            ];
        }

        if ($this->duplicateCount > 0) {
            $errors['duplicates'] = [
                'message' => "Se saltaron {$this->duplicateCount} registro(s) duplicado(s).",
                'count' => $this->duplicateCount,
                'rows' => $this->duplicateRows,
            ];
        }

        return $errors;
    }

    private function logSkippedRow(int $rowNumber, string $reason, array $data = []): void
    {
        $this->skippedCount++;

        $label = $this->getReasonLabel($reason);
        if (isset($data['missing_fields'])) {
            $label .= ': ' . $data['missing_fields'];
        }

        if (count($this->skippedRows) < 50) {
            $this->skippedRows[] = [
                'row' => $rowNumber,
                'reason' => $reason,
                'reason_label' => $label,
            ];
        }

        Log::debug('OperatorReportImport: Fila saltada', [
            'import_id' => $this->importId,
            'row_number' => $rowNumber,
            'reason' => $reason,
            'data' => $data,
        ]);
    }

    private function getReasonLabel(string $reason): string
    {
        return match ($reason) {
            'missing_phone_number' => 'Falta número de teléfono',
            'missing_coid' => 'Falta COID',
            'invalid_data' => 'Datos inválidos',
            'missing_data' => 'Faltan datos requeridos',
            'invalid_date' => 'Fechas invカlidas',
            default => $reason,
        };
    }

    private function logProcessedRow(int $rowNumber, ?int $simcardId): void
    {
        $this->insertedCount++;

        Log::info('OperatorReportImport: Fila procesada', [
            'import_id' => $this->importId,
            'row_number' => $rowNumber,
            'simcard_id' => $simcardId,
        ]);
    }

    private function logDuplicateRow(int $rowNumber, ?string $phoneNumber, ?string $coid): void
    {
        $this->duplicateCount++;

        if (count($this->duplicateRows) < 50) {
            $this->duplicateRows[] = [
                'row' => $rowNumber,
                'phone' => $phoneNumber,
                'coid' => $coid,
            ];
        }

        Log::debug('OperatorReportImport: Fila duplicada', [
            'import_id' => $this->importId,
            'row_number' => $rowNumber,
            'phone_number' => $phoneNumber,
            'coid' => $coid,
        ]);
    }

    private function buildDuplicateKey(RowDataExtractor $extractor, string $phoneNumber): ?string
    {
        $coid = $extractor->getString('coid');
        if ($coid) {
            return 'coid|' . $coid;
        }

        $rawData = $extractor->getRawData();
        $iccidRaw = $rawData['iccid'] ?? null;
        if ($iccidRaw !== null && $iccidRaw !== '') {
            $iccid = IccidCleanerService::clean((string) $iccidRaw);
            if ($iccid) {
                return 'iccid|' . $iccid;
            }
        }

        return $phoneNumber ? 'phone|' . $phoneNumber : null;
    }
}
