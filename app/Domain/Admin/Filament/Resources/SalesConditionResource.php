<?php

namespace App\Domain\Admin\Filament\Resources;

use App\Domain\Admin\Filament\Resources\SalesConditionResource\Pages;
use App\Domain\Import\Models\SalesCondition;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;

class SalesConditionResource extends Resource
{
    protected static ?string $model = SalesCondition::class;

    protected static ?string $slug = 'matches/{simcard}/sales-conditions';

    protected static bool $shouldRegisterNavigation = false;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-document-text';
    }

    public static function getNavigationLabel(): string
    {
        return 'Condiciones de Venta';
    }

    public static function getModelLabel(): string
    {
        return 'Condición de Venta';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Condiciones de Venta';
    }

    public static function getIndexUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?Model $tenant = null, bool $shouldGuessMissingParameters = false): string
    {
        return \App\Domain\Admin\Filament\Resources\SimcardResource::getUrl('index', $parameters, $isAbsolute, $panel, $tenant);
    }

    public static function getPages(): array
    {
        return [
            'edit' => Pages\EditSalesCondition::route('/{record}/edit'),
        ];
    }
}
