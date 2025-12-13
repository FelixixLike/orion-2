<?php

namespace App\Domain\Admin\Filament\Resources\RedemptionProductResource\Pages;

use App\Domain\Admin\Filament\Resources\RedemptionProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRedemptionProducts extends ListRecords
{
    protected static string $resource = RedemptionProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getBreadcrumb(): ?string
    {
        // Evita mostrar el texto "Listado" en el breadcrumb.
        return null;
    }

    public function getBreadcrumbs(): array
    {
        // Oculta el trail "Productos redimibles > Listado"
        return [];
    }
}
