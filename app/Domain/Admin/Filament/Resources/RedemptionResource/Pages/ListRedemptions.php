<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Admin\Filament\Resources\RedemptionResource\Pages;

use App\Domain\Admin\Filament\Resources\RedemptionResource;
use App\Domain\Store\Exports\RedemptionsExport;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListRedemptions extends ListRecords
{
    protected static string $resource = RedemptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export')
                ->label('Exportar Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->action(function () {
                    $query = $this->getFilteredTableQuery();

                    $fileName = 'redenciones-' . now()->format('Y-m-d_H-i') . '.xlsx';

                    return Excel::download(new RedemptionsExport($query), $fileName);
                }),
        ];
    }

    public function getBreadcrumb(): ?string
    {
        // Evita mostrar el texto "Listado" en el breadcrumb.
        return null;
    }

    public function getBreadcrumbs(): array
    {
        // Oculta el trail "Redenciones > Listado"
        return [];
    }
}
