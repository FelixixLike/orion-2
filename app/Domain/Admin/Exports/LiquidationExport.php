<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Admin\Exports;

use App\Domain\Store\Models\Liquidation;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LiquidationExport implements FromArray, ShouldAutoSize, WithStyles
{
    public function __construct(protected Liquidation $liquidation)
    {
    }

    public function array(): array
    {
        $liquidation = $this->liquidation;

        $rows = [
            ['Liquidacion'],
            ['Tienda', $liquidation->store?->name, $liquidation->store?->idpos],
            ['Periodo', sprintf('%04d-%02d', $liquidation->period_year, $liquidation->period_month)],
            ['Version', $liquidation->version],
            ['Estado', $liquidation->status],
            ['Total comision Claro', $liquidation->items->sum('total_commission')],
            ['Total trasladado al tendero', $liquidation->net_amount],
            ['Lineas', $liquidation->items->count()],
            [],
            ['ICCID', 'Numero', 'Comision Claro', 'Base liquidada', 'Monto final', 'Condicion', 'Porcentaje Residual'],
        ];

        foreach ($liquidation->items as $item) {
            $rows[] = [
                $item->iccid,
                $item->phone_number,
                $item->total_commission,
                $item->base_liquidation_final,
                $item->final_amount ?? $item->residual_payment ?? 0,
                $item->residual_percentage,
                // The CSV didn't output residual_percentage in the same way, let me double check the CSV mapping
                // Original: $item->residual_percentage
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            10 => ['font' => ['bold' => true]], // The header row for items
        ];
    }
}
