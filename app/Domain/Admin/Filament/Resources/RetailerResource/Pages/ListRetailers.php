<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Admin\Filament\Resources\RetailerResource\Pages;

use App\Domain\Admin\Filament\Pages\RetailersPage;
use App\Domain\Admin\Exports\RetailersExport;
use App\Domain\Admin\Filament\Resources\RetailerResource;
use App\Domain\Admin\Filament\Resources\UserResource\Pages\ListUsers;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;

class ListRetailers extends ListUsers
{
    protected static string $resource = RetailerResource::class;

    protected string $view = 'filament.admin.retailer.retailer-list';

    public function getTitle(): string
    {
        return 'Tenderos';
    }

    public function getModuleUrl(): string
    {
        return RetailersPage::getUrl(panel: 'admin');
    }

    public function getRetailerCreateUrl(): string
    {
        return static::getResource()::getUrl('create');
    }

    protected function getHeaderActions(): array
    {
        // El boton de "Crear tendero" vive en la vista personalizada.
        return [];
    }

    protected function getTableQuery(): ?Builder
    {
        // Para el recurso de tenderos usamos directamente la query de RetailerResource,
        // sin soportar scopes adicionales.
        return RetailerResource::getEloquentQuery();
    }

    public function exportExcel()
    {
        $query = $this->getFilteredTableQuery() ?? RetailerResource::getEloquentQuery();

        $fileName = 'tenderos-' . now()->format('Y-m-d_H-i') . '.xlsx';

        return Excel::download(new RetailersExport($query), $fileName);
    }
}
