<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Admin\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Collection;

class SalesConditionsExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles, WithMapping
{
    public function __construct(protected Collection $salesConditions)
    {
    }

    public function headings(): array
    {
        return [
            'ICCID',
            'TELEFONO',
            'IDPOS',
            'VALOR',
            'RESIDUAL',
            'POBLACION',
            'FECHA VENTA',
            'Creado por',
        ];
    }

    public function collection(): Collection
    {
        return $this->salesConditions;
    }

    public function map($salesCondition): array
    {
        return [
            $salesCondition->iccid ?? '',
            $salesCondition->phone_number ?? '',
            $salesCondition->idpos ?? '',
            $salesCondition->sale_price ? number_format($salesCondition->sale_price, 0, ',', '.') . ' COP' : '',
            $salesCondition->commission_percentage ? $salesCondition->commission_percentage . '%' : '',
            $salesCondition->population ?? '',
            $salesCondition->period_date ? $salesCondition->period_date->format('d/m/Y') : '',
            $salesCondition->creator->name ?? '',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
