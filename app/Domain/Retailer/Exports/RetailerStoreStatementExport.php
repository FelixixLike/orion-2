<?php

namespace App\Domain\Retailer\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RetailerStoreStatementExport implements FromArray, ShouldAutoSize, WithStyles
{
    public function __construct(
        protected array $summary,
        protected array $movements,
        protected string $period
    ) {
    }

    public function array(): array
    {
        $rows = [
            ['Estado de cuenta'],
            ['Tienda', $this->summary['store_label'] ?? ''],
            ['Periodo', $this->period],
            ['Debitos', $this->summary['debits'] ?? 0],
            ['Ajustes', $this->summary['adjustments'] ?? 0],
            ['Saldo final', $this->summary['final'] ?? 0],
            [], // Separator
            ['Fecha', 'Tipo', 'Descripcion', 'Monto', 'Saldo acumulado'],
        ];

        foreach ($this->movements as $m) {
            $rows[] = [
                $m['date'] ?? '',
                $m['type_label'] ?? '',
                $m['description'] ?? '',
                $m['amount'] ?? 0,
                $m['balance'] ?? '',
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            8 => ['font' => ['bold' => true]], // Header row for table
        ];
    }
}
