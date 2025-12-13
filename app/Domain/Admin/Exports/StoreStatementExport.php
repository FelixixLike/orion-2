<?php

namespace App\Domain\Admin\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StoreStatementExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles
{
    public function __construct(protected array $movements)
    {
    }

    public function headings(): array
    {
        return [
            'Fecha',
            'Tipo',
            'Descripcion',
            'Monto',
            'Origen',
        ];
    }

    public function array(): array
    {
        $rows = [];
        foreach ($this->movements as $m) {
            $rows[] = [
                $m['date'] ?? '',
                $m['type_label'] ?? '',
                $m['description'] ?? '',
                $m['amount'] ?? 0,
                $m['source_label'] ?? '',
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
