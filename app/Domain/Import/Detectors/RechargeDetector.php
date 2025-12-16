<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
declare(strict_types=1);

namespace App\Domain\Import\Detectors;

use App\Domain\Import\Detectors\Contracts\ImportDetectorInterface;
use App\Domain\Import\Enums\ImportType;

class RechargeDetector implements ImportDetectorInterface
{
    public function getType(): string
    {
        return ImportType::RECHARGE->value;
    }

    public function matches(array $headers): bool
    {
        // Excluir archivos que tienen IDPOS (son Sales Conditions)
        $excludeColumns = [
            'idpos',
        ];

        foreach ($excludeColumns as $col) {
            if ($this->hasColumn($headers, $col)) {
                return false; // No es un archivo de recargas
            }
        }

        $requiredColumns = [
            'numero',
        ];

        $optionalColumns = [
            'valor recarga',
            'mes'
        ];

        $hasPhone = false;
        foreach ($requiredColumns as $col) {
            if ($this->hasColumn($headers, $col)) {
                $hasPhone = true;
                break;
            }
        }

        if (!$hasPhone) {
            return false;
        }

        foreach ($optionalColumns as $col) {
            if ($this->hasColumn($headers, $col)) {
                return true;
            }
        }

        return false;
    }

    private function hasColumn(array $headers, string $search): bool
    {
        $searchLower = strtolower(trim($search));
        
        foreach ($headers as $header) {
            $headerLower = strtolower(trim((string)$header));
            
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
