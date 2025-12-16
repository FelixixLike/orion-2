<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
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

use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\AfterChunk;
use App\Domain\Import\Services\ImportProcessorService;

class OperatorReportImport implements OnEachRow, WithHeadingRow, SkipsEmptyRows, WithFormatData, WithMultipleSheets, WithChunkReading, ShouldQueue, WithEvents
{
    use Importable;

    private int $chunkProcessedCount = 0;
    private int $chunkErrorCount = 0; // Incluye duplicados y fallos
    private array $chunkErrors = [];

    private bool $headersValidated = false;
    private array $seenKeys = [];

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
                } catch (\Throwable $e) {
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
                if ($this->importId) {
                    if ($this->chunkProcessedCount > 0) {
                        Import::where('id', $this->importId)->increment('processed_rows', $this->chunkProcessedCount);
                    }
                    if ($this->chunkErrorCount > 0) {
                        Import::where('id', $this->importId)->increment('failed_rows', $this->chunkErrorCount);
                    }

                    if (!empty($this->chunkErrors)) {
                        $import = Import::find($this->importId);
                        $currentErrors = $import->errors ?? [];

                        if (isset($this->chunkErrors['summary'])) {
                            foreach ($this->chunkErrors['summary'] as $key => $val) {
                                $currentErrors['summary'][$key] = ($currentErrors['summary'][$key] ?? 0) + $val;
                            }
                        }

                        if (isset($this->chunkErrors['details'])) {
                            if (!isset($currentErrors['details']))
                                $currentErrors['details'] = [];
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

                $this->chunkProcessedCount = 0;
                $this->chunkErrorCount = 0;
                $this->chunkErrors = [];
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
        $rowIndex = $row->getIndex();

        try {
            if (!$this->headersValidated) {
                $this->validateHeaders($row);
                $this->headersValidated = true;
            }

            $rawOriginalRow = $row->toArray();
            $normalizedData = DataNormalizerService::normalizeKeys($rawOriginalRow);
            $extractor = RowDataExtractor::forOperatorReport($normalizedData);

            $missingFields = [];
            foreach ($this->requiredColumns as $field) {
                $val = $extractor->getString($field);
                if ($val === null || trim($val) === '')
                    $missingFields[] = $field;
            }

            if (!empty($missingFields)) {
                $translatedMissing = ColumnTranslator::translateMultiple($missingFields, ImportType::OPERATOR_REPORT->value);
                $this->logSkippedRow($rowIndex, 'missing_data', ['missing_fields' => implode(', ', $translatedMissing)]);
                return;
            }

            $phoneNumber = $extractor->getPhoneNumber('phone_number');
            $simcard = $this->findExistingSimcard($extractor, $phoneNumber);

            $this->createOperatorReport($extractor, $simcard, $phoneNumber, $rowIndex, $rawOriginalRow);

            // Éxito

        } catch (\Throwable $e) {
            $this->logSkippedRow($rowIndex, 'exception', ['message' => $e->getMessage()]);
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
            if (!in_array($requiredColumn, $availableColumns))
                $missingColumns[] = $requiredColumn;
        }

        if (!empty($missingColumns)) {
            $translations = ColumnTranslator::getTranslations(ImportType::OPERATOR_REPORT->value);
            throw new ImportColumnMappingException($missingColumns, $availableColumns, $translations);
        }
    }

    private function validateTemplateHeaders(array $rowData): void
    {
        $expectedNormalized = array_map([$this, 'normalizeHeaderName'], $this->templateHeaders);
        $availableOriginal = array_keys($rowData);
        $availableNormalized = array_map([$this, 'normalizeHeaderName'], $availableOriginal);

        $missing = array_values(array_diff($expectedNormalized, $availableNormalized));

        if (!empty($missing))
            throw new ImportValidationException("Faltan columnas en la plantilla.");
    }

    private function normalizeHeaderName(string $header): string
    {
        return OperatorReportSchema::normalizeHeader($header);
    }

    private function findExistingSimcard(RowDataExtractor $extractor, string $phoneNumber): ?Simcard
    {
        $normalizedData = $extractor->getRawData();
        $iccidRaw = $normalizedData['iccid'] ?? null;

        if ($iccidRaw !== null && $iccidRaw !== '') {
            $iccidString = is_float($iccidRaw) ? number_format($iccidRaw, 0, '', '') : (string) $iccidRaw;
            $iccidCleaned = IccidCleanerService::clean($iccidString);
            if ($iccidCleaned)
                return Simcard::where('iccid', $iccidCleaned)->first();
        }

        if ($phoneNumber !== null && $phoneNumber !== '')
            return Simcard::where('phone_number', $phoneNumber)->first();

        return null;
    }

    private function createOperatorReport(RowDataExtractor $extractor, $simcard, string $phoneNumber, int $rowIndex, array $rawOriginalRow): void
    {
        $activationDate = $extractor->getDate('activation_date');
        $cutoffDate = $extractor->getDate('cutoff_date');

        if (!$activationDate || !$cutoffDate) {
            $this->logSkippedRow($rowIndex, 'invalid_date');
            return;
        }

        $paymentPercentageRaw = $extractor->getDecimal('payment_percentage');
        $paymentPercentage = ($paymentPercentageRaw !== null && $paymentPercentageRaw > 1) ? $paymentPercentageRaw / 100 : $paymentPercentageRaw;

        $commission80 = $extractor->getDecimal('commission_paid_80');
        $commission20 = $extractor->getDecimal('commission_paid_20');
        $totalCommission = ($commission80 ?? 0) + ($commission20 ?? 0);
        $operatorAmount = $extractor->getDecimal('total_recharge_per_period');
        $montoCarga = $extractor->getDecimal('recharge_amount') ?? 0;
        $paymentRate = (float) ($paymentPercentage ?? 0);
        $calculated = $montoCarga * $paymentRate;
        $paidTotal = (float) ($commission80 ?? 0) + (float) ($commission20 ?? 0);
        $diff = $paidTotal - $calculated;

        $builder = (new OperatorReportBuilder());
        if ($simcard)
            $builder->forSimcard($simcard);

        $rawPayload = OperatorReportSchema::normalizeRow($rawOriginalRow);

        $iccidRaw = $extractor->getString('iccid');
        $iccidCleaned = $iccidRaw ? IccidCleanerService::clean($iccidRaw) : null;
        if ($iccidCleaned)
            $rawPayload['iccid'] = $iccidCleaned;

        $builder
            ->withIccid($iccidCleaned)
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

    // --- Helpers de Reporte ---

    private function logSkippedRow(int $rowNumber, string $reason, array $data = []): void
    {
        $this->chunkErrorCount++;
        if (!isset($this->chunkErrors['summary']['skipped']))
            $this->chunkErrors['summary']['skipped'] = 0;
        $this->chunkErrors['summary']['skipped']++;

        $this->chunkErrors['details'][] = [
            'row' => $rowNumber,
            'type' => 'skipped',
            'reason' => $reason,
            'info' => $data,
        ];
    }
}
