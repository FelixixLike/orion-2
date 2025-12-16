<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
declare(strict_types=1);

namespace App\Domain\Import\Enums;

enum ImportType: string
{
    case OPERATOR_REPORT = 'operator_report';
    case RECHARGE = 'recharge';
    case SALES_CONDITION = 'sales_condition';
    case STORE = 'store';
    case REDEMPTION_PRODUCT = 'redemption_product';

    public function label(): string
    {
        return match($this) {
            self::OPERATOR_REPORT => 'Reporte del Operador',
            self::RECHARGE => 'Recargas Variables',
            self::SALES_CONDITION => 'Condiciones de Venta',
            self::STORE => 'Tiendas',
            self::REDEMPTION_PRODUCT => 'Productos redimibles',
        };
    }

    public function badgeColor(): string
    {
        return match($this) {
            self::OPERATOR_REPORT => 'primary',
            self::RECHARGE => 'success',
            self::SALES_CONDITION => 'warning',
            self::STORE => 'info',
            self::REDEMPTION_PRODUCT => 'gray',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::OPERATOR_REPORT => 'heroicon-o-document-text',
            self::RECHARGE => 'heroicon-o-currency-dollar',
            self::SALES_CONDITION => 'heroicon-o-shopping-cart',
            self::STORE => 'heroicon-o-building-storefront',
            self::REDEMPTION_PRODUCT => 'heroicon-o-gift',
        };
    }

    /**
     * Obtiene la clase de importación asociada.
     */
    public function importClass(): string
    {
        return match($this) {
            self::OPERATOR_REPORT => \App\Domain\Import\Imports\OperatorReportImport::class,
            self::RECHARGE => \App\Domain\Import\Imports\RechargeImport::class,
            self::SALES_CONDITION => \App\Domain\Import\Imports\SalesConditionImport::class,
            self::STORE => \App\Domain\Import\Imports\StoreImport::class,
            self::REDEMPTION_PRODUCT => \App\Domain\Import\Imports\RedemptionProductImport::class,
        };
    }

    /**
     * Obtiene la clase del Job asociado.
     */
    public function jobClass(): string
    {
        return match($this) {
            self::OPERATOR_REPORT => \App\Domain\Import\Jobs\ProcessOperatorReportImportJob::class,
            self::RECHARGE => \App\Domain\Import\Jobs\ProcessRechargeImportJob::class,
            self::SALES_CONDITION => \App\Domain\Import\Jobs\ProcessSalesConditionImportJob::class,
            self::STORE => \App\Domain\Import\Jobs\ProcessStoreImportJob::class,
            self::REDEMPTION_PRODUCT => \App\Domain\Import\Jobs\ProcessRedemptionProductImportJob::class,
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type) => [$type->value => $type->label()])
            ->toArray();
    }
}
