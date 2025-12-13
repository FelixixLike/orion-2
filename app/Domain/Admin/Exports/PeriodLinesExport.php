<?php

namespace App\Domain\Admin\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PeriodLinesExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles
{
    public function __construct(protected array $lines)
    {
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

    public function array(): array
    {
        $rows = [];
        foreach ($this->lines as $row) {
            $rows[] = [
                $row['phone_number'] ?? '',
                $row['iccid'] ?? '',
                $row['idpos'] ?? '',
                $row['total_commission'] ?? 0,
                $row['operator_total_recharge'] ?? 0,
                $row['movilco_recharge_amount'] ?? 0,
                $row['base_liquidation_final'] ?? 0,
                $row['residual_percentage'] ?? 0,
                $row['transfer_percentage'] ?? 0,
                $row['residual_payment'] ?? 0,
                $row['commission_status'] ?? '',
                $row['activation_date'] ?? '',
                $row['cutoff_date'] ?? '',
                $row['custcode'] ?? '',
                $row['period'] ?? '',
                $row['store'] ?? '',
            ];
        }
        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
