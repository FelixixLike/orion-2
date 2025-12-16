<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
declare(strict_types=1);

namespace App\Domain\Admin\Exports;

use App\Domain\Store\Models\LiquidationItem;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

class LiquidationItemsExporter extends Exporter
{
    public static function getModel(): string
    {
        return LiquidationItem::class;
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('phone_number')->label('Número de teléfono'),
            ExportColumn::make('iccid')->label('ICCID'),
            ExportColumn::make('idpos')->label('ID POS'),
            ExportColumn::make('total_commission')->label('Total comisión Claro'),
            ExportColumn::make('operator_total_recharge')->label('Valor total carga periodo'),
            ExportColumn::make('movilco_recharge_amount')->label('Recarga Movilco'),
            ExportColumn::make('discount_total_period')->label('DTO total carga periodo'),
            ExportColumn::make('discount_residual')->label('Dto residual Movilco'),
            ExportColumn::make('base_liquidation_final')->label('Base liquidación final'),
            ExportColumn::make('residual_percentage')->label('% Residual'),
            ExportColumn::make('transfer_percentage')->label('% Traslado'),
            ExportColumn::make('residual_payment')->label('Pago residual'),
            ExportColumn::make('commission_status')->label('Estatus comisión'),
            ExportColumn::make('activation_date')->label('Fecha activación'),
            ExportColumn::make('cutoff_date')->label('Fecha corte'),
            ExportColumn::make('custcode')->label('Custcode'),
            ExportColumn::make('period')->label('Periodo'),
        ];
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->orderBy('id');
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $failedRows = $export->getFailedRowsCount();

        $body = "Exportación completada: {$export->successful_rows} filas exitosas";

        if ($failedRows > 0) {
            $body .= ", {$failedRows} fallidas.";
        } else {
            $body .= ".";
        }

        return $body;
    }
}
