<?php

declare(strict_types=1);

namespace App\Domain\Admin\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class OperatorReportsHistoryExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    /**
     * @param array<int,array{key:string,label:string}> $columns
     * @param array<int,array<string,mixed>> $rows
     */
    public function __construct(
        protected array $columns,
        protected array $rows,
    ) {
    }

    public function collection(): Collection
    {
        return collect($this->rows)->map(function (array $row) {
            $record = [];

            foreach ($this->columns as $column) {
                $record[] = $row[$column['key']] ?? null;
            }

            $record[] = $row['total_pagado'] ?? null;
            $record[] = $row['calc_monto_porcentaje'] ?? null;
            $record[] = $row['diferencia_pago'] ?? null;

            return $record;
        });
    }

    public function headings(): array
    {
        $baseHeadings = array_map(
            fn (array $column) => $column['label'],
            $this->columns
        );

        return array_merge(
            $baseHeadings,
            [
                'Total pagado (80+20)',
                'Calculo (Monto*%)',
                'Diferencia (Pagado - Calculado)',
            ],
        );
    }
}
