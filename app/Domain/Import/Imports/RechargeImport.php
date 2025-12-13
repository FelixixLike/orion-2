<?php

declare(strict_types=1);

namespace App\Domain\Import\Imports;

use App\Domain\Import\Constants\ExcelColumnNames;
use App\Domain\Import\Enums\ImportType;
use App\Domain\Import\Exceptions\ImportColumnMappingException;
use App\Domain\Import\Exceptions\ImportValidationException;
use App\Domain\Import\Models\Import;
use App\Domain\Import\Models\Recharge;
use App\Domain\Import\Services\ColumnTranslator;
use App\Domain\Import\Services\IccidCleanerService;
use App\Domain\Import\Services\SimcardService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Row;

class RechargeImport implements OnEachRow, WithHeadingRow, SkipsEmptyRows, WithMultipleSheets
{
    use Importable;

    private int $insertedCount = 0;
    private int $skippedCount = 0;
    private int $duplicateCount = 0;
    private array $skippedRows = [];
    private array $duplicateRows = [];
    private bool $headersValidated = false;
    /**
     * Encabezados exactos de la hoja "Tabla" en Variables.xlsx.
     */
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

    public function __construct(
        private readonly int $importId
    ) {
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
                    $this->periodYear = null;
                    $this->periodMonth = null;
                    $this->periodLabel = null;
                }
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
        $rowData = $this->normalizeRowData($row->toArray());

        if (!$this->headersValidated) {
            $this->validateHeaders($rowData);
            $this->headersValidated = true;
        }

        if (!$this->validateRow($rowData, $row->getIndex())) {
            return;
        }

        $phoneNumber = $this->extractPhoneNumber($rowData);
        $iccid = $this->extractIccid($rowData);

        if (!$phoneNumber) {
            $this->logSkippedRow($row->getIndex(), 'missing_phone_number');
            return;
        }

        $simcard = null;
        if ($iccid) {
            $simcard = SimcardService::findOrCreateByIccid($iccid, $phoneNumber);
        }
        if (!$simcard) {
            $simcard = SimcardService::findByPhoneNumber($phoneNumber);
        }

        if (!$simcard) {
            $this->logSkippedRow($row->getIndex(), 'simcard_not_found', ['phone_number' => $phoneNumber]);
            return;
        }

        $rechargeAmount = $this->extractRechargeAmount($rowData);
        $periodDate = $this->extractPeriodDate($rowData);

        $dedupeKey = $this->buildDuplicateKey($iccid ?: $phoneNumber, $periodDate, $rechargeAmount);
        if (isset($this->seenKeys[$dedupeKey])) {
            $this->logDuplicateRow($row->getIndex(), $phoneNumber, $periodDate, $rechargeAmount);
            return;
        }
        $this->seenKeys[$dedupeKey] = true;

        $logData = [
            'import_id' => $this->importId,
            'simcard_id' => $simcard->id,
        ];

        $phoneColumn = $this->getMatchedKey($rowData, ExcelColumnNames::RECHARGE['phone_number']);
        if ($phoneColumn && $phoneNumber) {
            $logData[$phoneColumn] = $phoneNumber;
        }

        $rechargeAmountColumn = $this->getMatchedKey($rowData, ExcelColumnNames::RECHARGE['recharge_amount']);
        if ($rechargeAmountColumn && $rechargeAmount !== null) {
            $logData[$rechargeAmountColumn] = $rechargeAmount;
        }

        $periodDateColumn = $this->getMatchedKey($rowData, ExcelColumnNames::RECHARGE['period_date']);
        if ($periodDateColumn && $periodDate) {
            $logData[$periodDateColumn] = $periodDate;
        }

        Log::info('Import Recharge - Datos extraídos del Excel', $logData);

        Recharge::create([
            'simcard_id' => $simcard->id,
            'iccid' => $iccid,
            'phone_number' => $phoneNumber, // Corrección: Guardar el número de teléfono
            'recharge_amount' => $rechargeAmount,
            'period_date' => $periodDate,
            'period_year' => $this->resolvePeriodYear($periodDate),
            'period_month' => $this->resolvePeriodMonth($periodDate),
            'period_label' => $this->resolvePeriodLabel($periodDate),
            'import_id' => $this->importId,
            'created_by' => $this->createdBy,
        ]);

        $this->insertedCount++;
    }

    private function validateRow(array $rowData, int $rowIndex): bool
    {
        $required = ['phone_number', 'recharge_amount', 'period_date'];
        $missing = [];

        foreach ($required as $field) {
            $val = $this->getValueByKeys($rowData, ExcelColumnNames::RECHARGE[$field]);
            if ($val === null || trim($val) === '') {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            $translatedMissing = ColumnTranslator::translateMultiple($missing, ImportType::RECHARGE->value);
            $this->logSkippedRow($rowIndex, 'missing_data', ['missing_fields' => implode(', ', $translatedMissing)]);
            return false;
        }
        return true;
    }

    private function validateHeaders(array $rowData): void
    {
        $this->validateTemplateHeaders($rowData);

        $availableColumns = array_keys($rowData);
        $missingColumns = [];
        $requiredFields = ['phone_number', 'recharge_amount', 'period_date'];

        foreach ($requiredFields as $field) {
            $aliases = ExcelColumnNames::RECHARGE[$field];
            $found = false;
            foreach ($aliases as $alias) {
                if (array_key_exists($alias, $rowData)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $missingColumns[] = $field;
            }
        }

        if (!empty($missingColumns)) {
            $translations = ColumnTranslator::getTranslations(ImportType::RECHARGE->value);

            throw new ImportColumnMappingException(
                $missingColumns,
                $availableColumns,
                $translations
            );
        }
    }

    /**
     * Valida que los encabezados coincidan con la plantilla Variables.xlsx (hoja Tabla).
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

        // Solo validamos las columnas faltantes, ignoramos las desconocidas (extras)
        if (!empty($missing)) {
            $missingColumns = array_map(fn(string $key) => $expectedMap[$key] ?? $key, $missing);
            //$unknownColumns = array_map(fn(string $key) => $availableMap[$key] ?? $key, $unknown);

            $message = "El archivo no coincide con la plantilla.\n";
            $message .= 'Faltan columnas: ' . $this->formatList($missingColumns) . ".";
            //$message .= 'Columnas desconocidas: ' . $this->formatList($unknownColumns) . '.';

            throw new ImportValidationException($message);
        }
    }

    private function normalizeHeaderName(string $header): string
    {
        $normalized = strtolower(trim($header));
        $normalized = str_replace([' ', '-', '.', "\t"], '_', $normalized);
        $normalized = preg_replace('/_+/', '_', $normalized);

        return $normalized ?? '';
    }

    private function formatList(array $items): string
    {
        if (empty($items)) {
            return 'ninguna';
        }

        return implode(', ', $items);
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

        Log::debug('RechargeImport: Fila saltada', [
            'import_id' => $this->importId,
            'row_number' => $rowNumber,
            'reason' => $reason,
            'data' => $data,
        ]);
    }

    private function logDuplicateRow(int $rowNumber, ?string $phoneNumber, ?string $periodDate, ?float $amount): void
    {
        $this->duplicateCount++;

        if (count($this->duplicateRows) < 50) {
            $this->duplicateRows[] = [
                'row' => $rowNumber,
                'phone' => $phoneNumber,
                'period_date' => $periodDate,
                'amount' => $amount,
            ];
        }

        Log::debug('RechargeImport: Registro duplicado', [
            'import_id' => $this->importId,
            'row_number' => $rowNumber,
            'phone_number' => $phoneNumber,
            'period_date' => $periodDate,
            'amount' => $amount,
        ]);
    }

    private function getReasonLabel(string $reason): string
    {
        return match ($reason) {
            'missing_phone_number' => 'Falta número de teléfono',
            'missing_data' => 'Faltan datos requeridos',
            'invalid_data' => 'Datos inválidos',
            'simcard_not_found' => 'Simcard no encontrada (primero cargue el reporte del operador)',
            default => $reason,
        };
    }

    private function normalizeRowData(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            $normalizedKey = strtolower(trim((string) $key));
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
        return $this->parseDecimal($this->getValueByKeys($rowData, ExcelColumnNames::RECHARGE['recharge_amount']));
    }

    private function extractIccid(array $rowData): ?string
    {
        $iccidRaw = $this->getValueByKeys($rowData, ExcelColumnNames::RECHARGE['iccid'] ?? []);
        if (!$iccidRaw) {
            return null;
        }

        // Corrección: NO usar IccidCleanerService ya que recorta caracteres necesarios.
        // Solo limpiamos espacios.
        $cleaned = trim((string) $iccidRaw);
        return $cleaned ?: null;
    }

    private function extractPeriodDate(array $rowData): ?string
    {
        $value = $this->getValueByKeys($rowData, ExcelColumnNames::RECHARGE['period_date']);

        if ($value === null || $value === '') {
            return Carbon::now()->format('Y-m-d');
        }

        $parsed = $this->parseDate($value);

        return $parsed ?? Carbon::now()->format('Y-m-d');
    }

    private function getValueByKeys(array $rowData, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $rowData[$key] ?? null;
            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        return null;
    }

    private function getMatchedKey(array $rowData, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $rowData[$key] ?? null;
            if ($value !== null && $value !== '') {
                return $key;
            }
        }

        return null;
    }

    private function parseDecimal(?string $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $cleaned = preg_replace('/[^\d.,]/', '', (string) $value);
        $cleaned = str_replace(',', '.', $cleaned);

        $float = (float) $cleaned;

        return $float > 0 ? $float : null;
    }

    private function parseDate(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $date = Carbon::parse($value);
            return $date->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function buildDuplicateKey(string $phoneNumber, ?string $periodDate, ?float $amount): string
    {
        return implode('|', [$phoneNumber, $periodDate, $amount]);
    }

    private function resolvePeriodYear(?string $periodDate): ?int
    {
        if ($this->periodYear !== null) {
            return $this->periodYear;
        }

        $date = $this->parseCarbon($periodDate);

        return $date?->year;
    }

    private function resolvePeriodMonth(?string $periodDate): ?int
    {
        if ($this->periodMonth !== null) {
            return $this->periodMonth;
        }

        $date = $this->parseCarbon($periodDate);

        return $date?->month;
    }

    private function resolvePeriodLabel(?string $periodDate): ?string
    {
        if ($this->periodLabel !== null) {
            return $this->periodLabel;
        }

        $date = $this->parseCarbon($periodDate);

        return $date?->format('Y-m');
    }

    private function parseCarbon(?string $value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
