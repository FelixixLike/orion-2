<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Admin\Filament\Resources\SimcardResource\Pages;

use App\Domain\Admin\Filament\Resources\SimcardResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;

class ListSimcards extends ListRecords
{
    protected static string $resource = SimcardResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getHeaderWidgets(): array
    {
        return [
            \App\Domain\Admin\Filament\Widgets\CrucesGlobalSummaryWidget::class,
        ];
    }
}
