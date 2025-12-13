<?php

declare(strict_types=1);

namespace App\Domain\Admin\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class RechargesHistoryExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    public function __construct(
        protected array $rows
    ) {
    }

    public function collection(): Collection
    {
        return collect($this->rows)->map(function (array $row) {
            return [
                $row['iccid'] ?? null,
                $row['phone_number'] ?? null,
                $row['recharge_amount'] ?? 0,
                $row['period_label'] ?? $row['period_date'] ?? null,
                $row['import_id'] ?? null,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'ICCID',
            'Teléfono',
            'Monto recarga',
            'Periodo',
            'Import ID',
        ];
    }
}
