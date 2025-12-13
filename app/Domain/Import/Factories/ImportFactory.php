<?php

declare(strict_types=1);

namespace App\Domain\Import\Factories;

use App\Domain\Import\Enums\ImportType;
use App\Domain\Import\Imports\OperatorReportImport;
use App\Domain\Import\Imports\RechargeImport;
use App\Domain\Import\Imports\RedemptionProductImport;
use App\Domain\Import\Imports\SalesConditionImport;
use App\Domain\Import\Imports\StoreImport;
use App\Domain\Import\Models\Import;

class ImportFactory
{
    /**
     * Crea la instancia del importador según el tipo
     *
     * @param string $type
     * @param int $importId
     * @return object
     * @throws \Exception
     */
    public static function create(string $type, int $importId): object
    {
        $importType = ImportType::from($type);

        return match ($importType) {
            ImportType::OPERATOR_REPORT => new OperatorReportImport($importId),
            ImportType::RECHARGE => new RechargeImport($importId),
            ImportType::SALES_CONDITION => new SalesConditionImport($importId),
            ImportType::STORE => new StoreImport(
                importId: $importId,
                updateConflictingUsers: (bool) (Import::find($importId)?->errors['options']['update_conflicting_users'] ?? false),
            ),
            ImportType::REDEMPTION_PRODUCT => new RedemptionProductImport($importId),
        };
    }
}
