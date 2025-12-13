<?php

declare(strict_types=1);

namespace App\Domain\Import\Detectors;

use App\Domain\Import\Detectors\Contracts\ImportDetectorInterface;
use App\Domain\Import\Enums\ImportType;

class OperatorReportDetector implements ImportDetectorInterface
{
    public function getType(): string
    {
        return ImportType::OPERATOR_REPORT->value;
    }

    public function matches(array $headers): bool
    {
        $requiredColumns = [
            'iccid',
            'coid',
            'comisionpagadapor80', // Clave para distinguir de Recargas
        ];

        $optionalColumns = [
            'numerodetelefono',
            'comisionpagadapor80',
            'comisionpagadapor20',
            'comision_pagada_por_20',
            'comision pagada por 20',
            'recharge_amount',
            'monto_carga',
            'monto carga',
            'recharge_period',
            'periododelacarga',
            'periodo de la carga',
            'total_recharge_per_period',
            'valortotcargporperiodo',
            'valor tot carg por periodo',
            'custcode',
            'codigohijo',
        ];

        $requiredFound = 0;
        foreach ($requiredColumns as $col) {
            if ($this->hasColumn($headers, $col)) {
                $requiredFound++;
            }
        }

        // Debe tener al menos 2 columnas requeridas
        if ($requiredFound < 2) {
            return false;
        }

        // Debe tener al menos 1 columna opcional característica
        $optionalFound = 0;
        foreach ($optionalColumns as $col) {
            if ($this->hasColumn($headers, $col)) {
                $optionalFound++;
            }
        }

        return $optionalFound >= 1;
    }

    private function hasColumn(array $headers, string $search): bool
    {
        $searchLower = strtolower(trim($search));

        foreach ($headers as $header) {
            $headerLower = strtolower(trim((string) $header));

            if ($headerLower === $searchLower) {
                return true;
            }

            if (str_contains($headerLower, $searchLower) || str_contains($searchLower, $headerLower)) {
                return true;
            }
        }

        return false;
    }
}
