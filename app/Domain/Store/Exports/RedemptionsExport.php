<?php

namespace App\Domain\Store\Exports;

use App\Domain\Store\Models\Redemption;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class RedemptionsExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        private readonly Builder $query,
    ) {
    }

    public function collection()
    {
        return $this->query
            ->with([
                'store.tenderer:id,id_number,first_name,last_name',
                'redemptionProduct:id,name',
            ])
            ->orderByDesc('requested_at')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Fecha solicitud',
            'Cédula tendero',
            'ID_PDV',
            'Tienda',
            'Producto',
            'Cantidad',
            'Valor total',
            'Estado',
        ];
    }

    /**
     * @param  \App\Domain\Store\Models\Redemption  $redemption
     */
    public function map($redemption): array
    {
        return [
            optional($redemption->requested_at)->format('Y-m-d H:i'),
            $redemption->store?->tenderer?->id_number ?? '',
            $redemption->store?->idpos ?? '',
            $redemption->store?->name ?? '',
            $redemption->redemptionProduct?->name ?? '',
            $redemption->quantity,
            $redemption->total_value,
            $redemption->status,
        ];
    }
}

