<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Import\Detectors;

use App\Domain\Import\Detectors\Contracts\ImportDetectorInterface;
use App\Domain\Import\Enums\ImportType;

class StoreDetector implements ImportDetectorInterface
{
    /**
     * Coincide si encuentra las columnas clave de Tiendas.xlsx
     */
    public function matches(array $headers): bool
    {
        $headers = array_map(fn ($h) => strtolower(trim((string) $h)), $headers);
        $required = ['id_pdv', 'nombre_punto', 'ruta', 'direccion', 'correo_electronico'];

        foreach ($required as $column) {
            if (!in_array($column, $headers, true)) {
                return false;
            }
        }

        return true;
    }

    public function getType(): string
    {
        return ImportType::STORE->value;
    }
}
