<?php

declare(strict_types=1);

namespace App\Domain\Import\Detectors;

use App\Domain\Import\Detectors\Contracts\ImportDetectorInterface;
use App\Domain\Import\Enums\ImportType;

class RedemptionProductDetector implements ImportDetectorInterface
{
    public function getType(): string
    {
        return ImportType::REDEMPTION_PRODUCT->value;
    }

    public function matches(array $headers): bool
    {
        $required = [
            'name',
            'type',
            'description',
        ];

        $numericColumns = [
            'unit_value',
            'stock',
            'monthly_store_limit',
            'max_value',
        ];

        $normalized = array_map(fn ($header) => strtolower(trim((string)$header)), $headers);

        $hasRequired = collect($required)->every(fn ($column) => in_array($column, $normalized, true));

        if (! $hasRequired) {
            return false;
        }

        $hasNumericColumns = collect($numericColumns)
            ->filter(fn ($column) => in_array($column, $normalized, true))
            ->isNotEmpty();

        $hasActiveColumn = in_array('is_active', $normalized, true);

        return $hasNumericColumns && $hasActiveColumn;
    }
}
