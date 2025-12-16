<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
declare(strict_types=1);

namespace App\Domain\Import\Services;

use App\Domain\Import\Constants\ExcelColumnNames;
use Illuminate\Support\Carbon;

class RowDataExtractor
{
    public function __construct(
        private readonly array $rowData,
        private readonly array $columnMappings
    ) {}

    /**
     * Extrae un valor de string limpio.
     */
    public function getString(string $fieldKey): ?string
    {
        $value = $this->getRawValue($fieldKey);
        
        // Si es un DateTime que representa un número pequeño (1-31),
        // extraer solo el día en lugar de la fecha completa
        if ($value instanceof \DateTime) {
            $day = (int)$value->format('d');
            // Si el día está entre 1-31 y el año es 1970 o 1900, probablemente es un número, no una fecha
            if ($day >= 1 && $day <= 31 && ((int)$value->format('Y') === 1970 || (int)$value->format('Y') === 1900)) {
                return (string)$day;
            }
        }
        
        return DataNormalizerService::cleanString($value);
    }

    /**
     * Extrae un número de teléfono limpio.
     */
    public function getPhoneNumber(string $fieldKey): ?string
    {
        $value = $this->getRawValue($fieldKey);
        return DataNormalizerService::cleanPhoneNumber($value);
    }

    /**
     * Extrae un decimal.
     */
    public function getDecimal(string $fieldKey): ?float
    {
        $value = $this->getRawValue($fieldKey);
        return DataNormalizerService::parseDecimal($value);
    }

    /**
     * Extrae una fecha.
     */
    public function getDate(string $fieldKey): ?Carbon
    {
        $value = $this->getRawValue($fieldKey);
        return DateParserService::parse($value);
    }

    /**
     * Obtiene el valor raw buscando en las posibles variaciones de columna.
     */
    private function getRawValue(string $fieldKey): mixed
    {
        if (!isset($this->columnMappings[$fieldKey])) {
            return null;
        }

        $possibleKeys = $this->columnMappings[$fieldKey];

        foreach ($possibleKeys as $key) {
            $normalizedKey = strtolower(trim($key));
            
            if (isset($this->rowData[$normalizedKey])) {
                return $this->rowData[$normalizedKey];
            }
        }

        return null;
    }

    /**
     * Obtiene los datos raw sin procesar.
     */
    public function getRawData(): array
    {
        return $this->rowData;
    }

    /**
     * Factory method para Operator Reports.
     */
    public static function forOperatorReport(array $rowData): self
    {
        return new self($rowData, ExcelColumnNames::OPERATOR_REPORT);
    }
}
