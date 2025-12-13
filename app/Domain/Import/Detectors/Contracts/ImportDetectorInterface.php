<?php

declare(strict_types=1);

namespace App\Domain\Import\Detectors\Contracts;

interface ImportDetectorInterface
{
    /**
     * Determina si los encabezados coinciden con este tipo de importación.
     *
     * @param array $headers
     * @return bool
     */
    public function matches(array $headers): bool;

    /**
     * Obtiene el identificador del tipo de importación (ej. 'operator_report').
     *
     * @return string
     */
    public function getType(): string;
}
