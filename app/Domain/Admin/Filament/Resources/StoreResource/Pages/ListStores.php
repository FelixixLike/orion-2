<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Admin\Filament\Resources\StoreResource\Pages;

use App\Domain\Admin\Filament\Resources\StoreResource;
use App\Domain\Store\Exports\StoresExport;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListStores extends ListRecords
{
    protected static string $resource = StoreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create')
                ->label('Crear Tienda')
                ->icon('heroicon-o-plus')
                ->color('success')
                ->url(StoreResource::getUrl('create')),
            Actions\Action::make('export')
                ->label('Exportar Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->action(function () {
                    $query = $this->getFilteredTableQuery();

                    $fileName = 'tiendas-' . now()->format('Y-m-d_H-i') . '.xlsx';

                    return Excel::download(new StoresExport($query), $fileName);
                }),
        ];
    }
}
