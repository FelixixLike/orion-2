<?php

namespace App\Domain\Admin\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ManagementReportExport implements FromArray, ShouldAutoSize, WithStyles
{
    public function __construct(protected array $summary)
    {
    }

    public function array(): array
    {
        $summary = $this->summary;

        return [
            ['Reporte Gerencial'],
            ['Periodo', $summary['period'] ?? ''],
            ['Total Claro', $summary['claro_total'] ?? 0],
            ['Total trasladado a tenderos', $summary['tendero_total'] ?? 0],
            ['Diferencia', $summary['difference'] ?? 0],
            ['Tiendas liquidadas', $summary['stores_count'] ?? 0],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            // Apply currency format to rows 3, 4, 5 column B?
            // "B3:B5" => ['numberFormat' => ['formatCode' => '#,##0.00']]
            // But let's keep it simple for now.
        ];
    }
}
