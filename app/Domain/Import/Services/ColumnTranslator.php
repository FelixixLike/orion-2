<?php

declare(strict_types=1);

namespace App\Domain\Import\Services;

use App\Domain\Import\Enums\ImportType;

/**
 * Traduce nombres técnicos de columnas a etiquetas en español.
 */
class ColumnTranslator
{
    private const OPERATOR_REPORT_COLUMNS = [
        'phone_number' => 'Número de Teléfono',
        'coid' => 'COID',
        'city_code' => 'Código de Ciudad',
        'commission_status' => 'Estado de Comisión',
        'activation_date' => 'Fecha de Activación',
        'cutoff_date' => 'Fecha de Corte',
        'commission_paid_80' => 'Comisión Pagada 80%',
        'commission_paid_20' => 'Comisión Pagada 20%',
        'total_recharge_per_period' => 'Total Recarga por Período',
        'total_commission' => 'Comisión Total (80+20)',
        'recharge_amount' => 'Monto de Recarga',
        'recharge_period' => 'Período de Recarga',
        'custcode' => 'Código de Cliente',
        'iccid' => 'ICCID',
    ];

    private const RECHARGE_COLUMNS = [
        'phone_number' => 'Número de Teléfono',
        'recharge_amount' => 'Monto de Recarga',
        'period_date' => 'Período (Mes)',
        'iccid' => 'ICCID',
        'recharge_date' => 'Fecha de Recarga',
        'transaction_id' => 'ID de Transacción',
        'status' => 'Estado',
    ];

    private const SALES_CONDITION_COLUMNS = [
        'phone_number' => 'Número de Teléfono',
        'iccid' => 'ICCID',
        'idpos' => 'IDPOS',
        'sale_price' => 'Valor SIM',
        'commission_percentage' => 'Porcentaje de Comisión',
        'period_date' => 'Mes',
    ];

    public static function getTranslations(string $importType): array
    {
        return match ($importType) {
            ImportType::OPERATOR_REPORT->value => self::OPERATOR_REPORT_COLUMNS,
            ImportType::RECHARGE->value => self::RECHARGE_COLUMNS,
            ImportType::SALES_CONDITION->value => self::SALES_CONDITION_COLUMNS,
            default => [],
        };
    }

    public static function translate(string $technicalName, string $importType): string
    {
        $translations = self::getTranslations($importType);
        return $translations[$technicalName] ?? $technicalName;
    }

    public static function translateMultiple(array $technicalNames, string $importType): array
    {
        return array_map(
            fn ($name) => self::translate($name, $importType),
            $technicalNames
        );
    }

    public static function getReverseTranslations(string $importType): array
    {
        return array_flip(self::getTranslations($importType));
    }

    public static function findTechnicalName(string $spanishName, string $importType): ?string
    {
        $reverse = self::getReverseTranslations($importType);
        return $reverse[$spanishName] ?? null;
    }

    public static function formatColumnList(array $columns, string $importType): string
    {
        $translated = self::translateMultiple($columns, $importType);

        return match (count($translated)) {
            0 => '',
            1 => $translated[0],
            2 => implode(' y ', $translated),
            default => implode(', ', array_slice($translated, 0, -1)) . ' y ' . end($translated),
        };
    }

    public static function getRequiredColumns(string $importType): array
    {
        return match ($importType) {
            ImportType::OPERATOR_REPORT->value => ['phone_number', 'coid'],
            ImportType::RECHARGE->value => ['phone_number', 'recharge_amount', 'period_date'],
            ImportType::SALES_CONDITION->value => ['phone_number', 'idpos', 'sale_price', 'commission_percentage', 'period_date'],
            default => [],
        };
    }

    public static function getRequiredColumnsFormatted(string $importType): string
    {
        return self::formatColumnList(self::getRequiredColumns($importType), $importType);
    }
}
