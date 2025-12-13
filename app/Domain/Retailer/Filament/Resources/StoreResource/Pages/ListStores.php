<?php

namespace App\Domain\Retailer\Filament\Resources\StoreResource\Pages;

use App\Domain\Retailer\Filament\Resources\StoreResource;
use Filament\Resources\Pages\ListRecords;

class ListStores extends ListRecords
{
    protected static string $resource = StoreResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
