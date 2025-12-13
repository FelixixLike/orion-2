<?php

declare(strict_types=1);

namespace App\Domain\Import\Services;

class IccidCleanerService
{
    /**
     * Limpia un ICCID removiendo los primeros 2 dígitos y el último dígito
     * 
     * Ejemplo: 89571017024119186125 -> 57101702411918612
     */
    public static function clean(string $iccid): ?string
    {
        if (empty($iccid) || strlen($iccid) < 4) {
            return null;
        }

        // Remover primeros 2 dígitos y último dígito
        $cleaned = substr($iccid, 2, -1);

        return $cleaned ?: null;
    }

    /**
     * Valida si un ICCID puede ser limpiado
     */
    public static function canClean(string $iccid): bool
    {
        return !empty($iccid) && strlen($iccid) >= 4;
    }
}

