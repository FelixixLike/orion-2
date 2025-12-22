<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Store\Exports;

use App\Domain\Store\Enums\Municipality;
use App\Domain\Store\Enums\StoreCategory;
use App\Domain\Store\Enums\StoreStatus;
use App\Domain\Store\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StoresExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(
        private readonly Builder $query,
    ) {
    }

    public function query()
    {
        return $this->query
            ->with(['tenderer', 'users']) // Optimized eager loading
            ->orderBy('idpos');
    }

    public function headings(): array
    {
        return [
            'ID_PDV',
            'Tienda',
            'Ruta',
            'Circuito',
            'Tendero',
            'Categoría',
            'Municipio',
            'Estado',
        ];
    }

    /**
     * @param  \App\Domain\Store\Models\Store  $store
     */
    public function map($store): array
    {
        $category = $store->category instanceof StoreCategory
            ? $store->category->label()
            : (StoreCategory::tryFrom($store->category)?->label() ?? (string) $store->category);

        $municipality = $store->municipality instanceof Municipality
            ? $store->municipality->label()
            : (Municipality::tryFrom($store->municipality)?->label() ?? (string) $store->municipality);

        $status = $store->status instanceof StoreStatus
            ? $store->status->label()
            : (StoreStatus::tryFrom($store->status)?->label() ?? (string) $store->status);

        return [
            $store->idpos,
            $store->name,
            $store->route_code,
            $store->circuit_code,
            $store->tenderer?->getFilamentName() ?? 'Sin asignar',
            $category,
            $municipality,
            $status,
        ];
    }
}

