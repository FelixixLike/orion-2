<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Admin\Exports;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PeriodLinesExport implements FromQuery, FromArray, WithHeadings, ShouldAutoSize, WithStyles, WithMapping, ShouldQueue
{
    use Exportable, Queueable;

    protected $query = null;
    protected $array = null;

    public function __construct($source)
    {
        if ($source instanceof Builder || $source instanceof EloquentBuilder) {
            $this->query = $source;
        } else {
            $this->array = $source;
        }
    }

    public function query()
    {
        return $this->query ?: null;
    }

    public function array(): array
    {
        return $this->array ?: [];
    }

    public function headings(): array
    {
        return [
            'Teléfono',
            'ICCID',
            'IDPOS',
            'Comisión Claro',
            'Valor carga periodo',
            'Recarga Movilco',
            'Base Liq Final',
            '% Residual',
            '% Traslado',
            'Pago residual',
            'Estatus comisión',
            'F. activación',
            'F. corte',
            'CUSTCODE',
            'Periodo',
            'Tienda'
        ];
    }

    public function map($row): array
    {
        // Si viene del array (Preview), ya viene mapeado en PeriodLinesDetailPage
        if (is_array($row)) {
            // Asegurar formato ICCID por si acaso
            if (isset($row['iccid']) && !str_starts_with($row['iccid'], ' ')) {
                $row['iccid'] = ' ' . $row['iccid'];
            }
            return array_values($row);
        }

        // Si viene del Query (LiquidationItem model)
        $store = $row->liquidation?->store;

        return [
            $row->phone_number ?? '',
            isset($row->iccid) ? ' ' . $row->iccid : '', // Espacio duro para evitar notación científica
            $row->idpos ?? '',
            $row->total_commission ?? 0,
            $row->operator_total_recharge ?? 0,
            $row->movilco_recharge_amount ?? 0,
            $row->base_liquidation_final ?? 0,
            $row->residual_percentage ?? 0,
            $row->transfer_percentage ?? 0,
            $row->residual_payment ?? 0,
            $row->commission_status ?? '',
            optional($row->activation_date)->format('Y-m-d') ?? '',
            optional($row->cutoff_date)->format('Y-m-d') ?? '',
            $row->custcode ?? '',
            $row->period ?? '',
            $store?->name ?? '',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}

