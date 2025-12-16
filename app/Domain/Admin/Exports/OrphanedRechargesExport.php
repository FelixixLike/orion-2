<?php

namespace App\Domain\Admin\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Collection;

class OrphanedRechargesExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles
{
    public function __construct(protected Collection $recharges)
    {
    }

    public function headings(): array
    {
        return [
            'Telefono',
            'ICCID',
            'Valor Recarga',
            'Fecha',
            'Mes Periodo',
        ];
    }

    public function collection(): Collection
    {
        return $this->recharges->map(function ($recharge) {
            return [
                $recharge->phone_number,
                $recharge->iccid ?? '',
                $recharge->recharge_amount,
                $recharge->period_date ? $recharge->period_date->format('d/m/Y') : '',
                $recharge->period_label,
            ];
        });
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
