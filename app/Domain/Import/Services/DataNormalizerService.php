<?php

declare(strict_types=1);

namespace App\Domain\Import\Services;

class DataNormalizerService
{
    /**
     * Mapeo de nombres de columnas en español a los nombres internos usados en los imports.
     */
    private static array $columnMapping = [
        'coid' => 'coid',
        'numerodetelefono' => 'phone_number',
        'numero' => 'phone_number',
        'comisionpagadapor80' => 'commission_paid_80',
        'conceptodecomision80' => 'commission_paid_80',
        'comisionpagadapor20' => 'commission_paid_20',
        'conceptodecomision20' => 'commission_paid_20',
        'estatusdecomision' => 'commission_status',
        'estatusfechadeactivacion' => 'commission_status', // Variante encontrada en Excel
        'fechadeactivacion' => 'activation_date',
        'fechadecorte' => 'cutoff_date',
        'custcode' => 'custcode',
        'iccid' => 'iccid',
        'valortotcargaporperiodo' => 'total_recharge_per_period',
        'valortotcargporperiodo' => 'total_recharge_per_period', // Sin 'A' en CARGA
        'valorrecarga' => 'recharge_amount',
        'valor recarga' => 'recharge_amount',
        'periododerecarga' => 'recharge_period',
        'periododelacarga' => 'recharge_period', // Variante: DE LA CARGA
        'montoderecarga' => 'recharge_amount',
        'monto_carga' => 'recharge_amount', // Variante: con guión bajo
        'montocarga' => 'recharge_amount', // Variante: sin guión
        'codigohijo' => 'city_code',
    ];

    /**
     * Normaliza las claves de un array (lowercase, sin espacios extra, y mapeo de columnas).
     */
    public static function normalizeKeys(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            if (is_numeric($key) && $value === null) {
                continue;
            }

            $normalizedKey = strtolower(trim((string) $key));

            if (isset(self::$columnMapping[$normalizedKey])) {
                $normalizedKey = self::$columnMapping[$normalizedKey];
            }

            $normalized[$normalizedKey] = $value;
        }

        return $normalized;
    }

    /**
     * Limpia un string (trim, null si vacío).
     * Maneja números grandes (como ICCIDs) sin perder precisión.
     */
    public static function cleanString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            $cleaned = (string) $value;
        } elseif (is_float($value)) {
            $cleaned = number_format($value, 0, '', '');
        } else {
            $cleaned = trim((string) $value);
        }

        return $cleaned === '' ? null : $cleaned;
    }

    /**
     * Limpia un número de teléfono (solo dígitos).
     */
    public static function cleanPhoneNumber(mixed $value): ?string
    {
        $cleaned = self::cleanString($value);

        if (!$cleaned) {
            return null;
        }

        $digitsOnly = preg_replace('/[^0-9]/', '', $cleaned);

        return $digitsOnly === '' ? null : $digitsOnly;
    }

    /**
     * Parsea un decimal (maneja comas y puntos).
     */
    public static function parseDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Si ya es numérico (float/int nativo de PHP/Excel), retornarlo directo
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $string = trim((string) $value);

        // Remover espacios y caracteres de moneda comunes
        $string = str_replace(['$', ' ', 'COP', 'USD'], '', $string);

        // Detección heurística de formato
        $hasDot = str_contains($string, '.');
        $hasComma = str_contains($string, ',');

        if ($hasDot && $hasComma) {
            // Caso complejo: tiene ambos. Decidir cuál es el decimal por la posición.
            $lastDotPos = strrpos($string, '.');
            $lastCommaPos = strrpos($string, ',');

            if ($lastDotPos < $lastCommaPos) {
                // Formato Europeo/Latino: 1.000,50
                // Eliminar puntos (miles) y cambiar coma por punto (decimal)
                $string = str_replace('.', '', $string);
                $string = str_replace(',', '.', $string);
            } else {
                // Formato Inglés: 1,000.50
                // Eliminar comas (miles)
                $string = str_replace(',', '', $string);
            }
        } elseif ($hasComma) {
            // Solo tiene comas: 10,50 o 1,000 (ambiguo, pero en contexto latino soler ser decimal)
            // Asumiremos que es decimal si hay solo una coma. Si hay varias ("1,000,000"), son miles.
            if (substr_count($string, ',') > 1) {
                $string = str_replace(',', '', $string);
            } else {
                $string = str_replace(',', '.', $string);
            }
        } elseif ($hasDot) {
            // Solo tiene puntos: 10.50 o 1.000 (ambiguo)
            // Si hay más de un punto, seguro son miles: 1.000.000 -> 1000000
            if (substr_count($string, '.') > 1) {
                $string = str_replace('.', '', $string);
            }
            // Si hay solo uno (10.50), PHP lo maneja bien nativamente.
        }

        if ($string === '' || !is_numeric($string)) {
            return null;
        }

        return (float) $string;
    }
}
