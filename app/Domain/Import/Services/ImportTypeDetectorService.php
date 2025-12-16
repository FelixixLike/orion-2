<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
declare(strict_types=1);

namespace App\Domain\Import\Services;

use App\Domain\Import\Detectors\OperatorReportDetector;
use App\Domain\Import\Detectors\RechargeDetector;
use App\Domain\Import\Detectors\RedemptionProductDetector;
use App\Domain\Import\Detectors\SalesConditionDetector;
use App\Domain\Import\Detectors\StoreDetector;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class ImportTypeDetectorService
{
    /**
     * @var array<int, string>
     */
    private static array $detectors = [
        OperatorReportDetector::class,
        RechargeDetector::class,
        SalesConditionDetector::class,
        StoreDetector::class,
        RedemptionProductDetector::class,
    ];

    /**
     * Detecta el tipo de importación basándose en las columnas del archivo
     * 
     * @return string|null 'operator_report'|'recharge'|'sales_condition'|null
     */
    public static function detect(string $filePath): ?string
    {
        try {
            $headers = self::getHeaders($filePath);
            
            if (empty($headers)) {
                return null;
            }

            // Normalizar headers a minúsculas
            $normalizedHeaders = array_map(fn($h) => strtolower(trim((string)$h)), $headers);

            foreach (self::$detectors as $detectorClass) {
                $detector = new $detectorClass();
                if ($detector->matches($normalizedHeaders)) {
                    return $detector->getType();
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::warning('Error detectando tipo de importación', [
                'file' => $filePath,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Obtiene los encabezados de la primera fila del Excel
     */
    private static function getHeaders(string $filePath): array
    {
        try {
            /** @var array<int, array<int, mixed>> $data */
            $data = Excel::toArray(new \stdClass(), $filePath);
            
            if (empty($data[0]) || empty($data[0][0])) {
                return [];
            }

            /** @var array<int, mixed> $firstRow */
            $firstRow = $data[0][0];
            return array_map(fn($v) => is_scalar($v) ? (string)$v : '', $firstRow);
        } catch (\Exception $e) {
            return [];
        }
    }
}
