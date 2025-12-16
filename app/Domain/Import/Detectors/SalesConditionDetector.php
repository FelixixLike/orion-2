<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
declare(strict_types=1);

namespace App\Domain\Import\Detectors;

use App\Domain\Import\Detectors\Contracts\ImportDetectorInterface;
use App\Domain\Import\Enums\ImportType;

class SalesConditionDetector implements ImportDetectorInterface
{
    public function getType(): string
    {
        return ImportType::SALES_CONDITION->value;
    }

    public function matches(array $headers): bool
    {
        // Headers requeridos por el usuario (normalizados a lowercase por el detector service)
        // ICCID, NUMERODETELEFONO, IDPOS, VALOR, RESIDUAL, POBLACION, FECHA VENTA
        $required = [
            'iccid',
            'numerodetelefono',
            'idpos',
            'valor',
            'residual',
            'poblacion',
            'fecha venta' // strtolower("FECHA VENTA") -> "fecha venta"
        ];

        // Verificamos presencia
        foreach ($required as $req) {
            $found = false;
            foreach ($headers as $h) {
                // Busqueda exacta o muy flexible? El usuario pidio "Encabezados correctos (obligatorios y exactos)"
                // Pero ImportTypeDetectorService normaliza usando trim y strtolower.
                // Asi que comparamos contra eso.
                if (trim(strtolower((string) $h)) === $req) {
                    $found = true;
                    break;
                }
            }
            if (!$found)
                return false;
        }

        return true;
    }
}
