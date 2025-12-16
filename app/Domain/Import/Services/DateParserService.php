<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
declare(strict_types=1);

namespace App\Domain\Import\Services;

use Illuminate\Support\Carbon;

class DateParserService
{
    /**
     * Formatos de fecha soportados (en orden de prioridad).
     */
    private const SUPPORTED_FORMATS = [
        'Y-m-d',
        'd/m/Y',
        'd-m-Y',
        'm/d/Y',
        'm-d-Y',
        'Y/m/d',
        'Ymd',
        'd/m/y',
        'd-m-y',
        'm/d/y',
        'm-d-y',
    ];

    /**
     * Parsea una fecha desde múltiples formatos posibles.
     */
    public static function parse(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Si ya es una instancia de Carbon, retornarla
        if ($value instanceof Carbon) {
            return $value;
        }

        // Si es un objeto DateTime, convertirlo
        if ($value instanceof \DateTime) {
            return Carbon::instance($value);
        }

        // Si es un número (serial de Excel), convertirlo
        if (is_numeric($value)) {
            return self::parseExcelSerialDate((float) $value);
        }

        $stringValue = trim((string) $value);

        if ($stringValue === '') {
            return null;
        }

        // Si el string es numérico (serial de Excel como string)
        if (is_numeric($stringValue)) {
            return self::parseExcelSerialDate((float) $stringValue);
        }

        // Manejo prioritario de formatos con barra (dd/mm/yyyy común en la región)
        if (str_contains($stringValue, '/')) {
            // Intentar formatos explícitos comunes
            $slashFormats = ['d/m/Y', 'Y/m/d', 'm/d/Y'];
            foreach ($slashFormats as $format) {
                try {
                    // StartOfDay para evitar horas residuales
                    return Carbon::createFromFormat($format, $stringValue)->startOfDay();
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        // Intentar parsear con cada formato soportado estándar
        foreach (self::SUPPORTED_FORMATS as $format) {
            try {
                $date = Carbon::createFromFormat($format, $stringValue);

                if ($date) {
                    return $date->startOfDay();
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        // Último intento: usar el parser flexible de Carbon
        try {
            return Carbon::parse($stringValue)->startOfDay();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Convierte un número serial de Excel a Carbon.
     * Excel almacena fechas como número de días desde 1900-01-01.
     */
    private static function parseExcelSerialDate(float $serial): ?Carbon
    {
        try {
            // Excel epoch: 1900-01-01 (pero Excel tiene un bug: considera 1900 como año bisiesto)
            // Por eso restamos 2 días en lugar de 1
            $unixTimestamp = ($serial - 25569) * 86400; // 25569 = días entre 1900-01-01 y 1970-01-01

            return Carbon::createFromTimestamp($unixTimestamp)->startOfDay();
        } catch (\Exception $e) {
            return null;
        }
    }
}
