<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
declare(strict_types=1);

namespace App\Domain\Admin\Exports;

use App\Domain\Store\Models\Liquidation;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class LiquidationsPeriodExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    public function __construct(
        private readonly int $year,
        private readonly ?int $month = null,
    ) {}

    public function collection(): Collection
    {
        $rows = Liquidation::query()
            ->with('store:id,idpos,name')
            ->withCount('items')
            ->when($this->year, fn($q) => $q->where('period_year', $this->year))
            ->when($this->month, fn($q) => $q->where('period_month', $this->month))
            ->orderBy('store_id')
            ->orderBy('period_month')
            ->get()
            ->map(function (Liquidation $liquidation) {
                return [
                    'tienda' => $liquidation->store?->name ?? $liquidation->store_id,
                    'idpos' => $liquidation->store?->idpos,
                    'periodo' => sprintf('%04d-%02d', $liquidation->period_year, $liquidation->period_month),
                    'version' => $liquidation->version ?? 1,
                    'estado' => $liquidation->status,
                    'monto_bruto' => $liquidation->gross_amount,
                    'monto_neto' => $liquidation->net_amount,
                    'simcards' => $liquidation->items_count,
                    'creada_el' => optional($liquidation->created_at)->format('Y-m-d H:i'),
                ];
            });

        if ($rows->isEmpty()) {
            return $rows;
        }

        $rows->push([
            'tienda' => 'TOTAL',
            'idpos' => '',
            'periodo' => $this->month ? sprintf('%04d-%02d', $this->year, $this->month) : $this->year,
            'versión' => '',
            'estado' => '',
            'monto_bruto' => $rows->sum('monto_bruto'),
            'monto_neto' => $rows->sum('monto_neto'),
            'simcards' => $rows->sum('simcards'),
            'creada_el' => '',
        ]);

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Tienda',
            'IDPOS',
            'Periodo',
            'Versión',
            'Estado',
            'Monto bruto',
            'Monto neto',
            'Simcards',
            'Creada el',
        ];
    }
}
