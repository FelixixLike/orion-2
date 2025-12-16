<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Admin\Filament\Resources;

use App\Domain\Admin\Filament\Resources\OperatorReportResource\Pages;
use App\Domain\Import\Models\OperatorReport;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Filament\Schemas\Schema;

class OperatorReportResource extends Resource
{
    protected static ?string $model = OperatorReport::class;

    protected static ?string $slug = 'matches/{simcard}/operator-reports';

    protected static bool $shouldRegisterNavigation = false;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-document-text';
    }

    public static function getNavigationLabel(): string
    {
        return 'Reportes del Operador';
    }

    public static function getModelLabel(): string
    {
        return 'Reporte del Operador';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Reportes del Operador';
    }

    public static function getIndexUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?Model $tenant = null, bool $shouldGuessMissingParameters = false): string
    {
        return \App\Domain\Admin\Filament\Resources\SimcardResource::getUrl('index', $parameters, $isAbsolute, $panel, $tenant);
    }

    public static function getPages(): array
    {
        return [
            'edit' => Pages\EditOperatorReport::route('/{record}/edit'),
        ];
    }
}
