<?php

namespace App\Domain\Admin\Filament\Resources\SalesConditionResource\Pages;

use App\Domain\Admin\Filament\Resources\SalesConditionResource;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Resources\Pages\EditRecord;

class EditSalesCondition extends EditRecord
{
    protected static string $resource = SalesConditionResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('idpos')
                    ->label('ID POS')
                    ->required(),

                TextInput::make('sale_price')
                    ->label('Precio Venta')
                    ->numeric()
                    ->prefix('$')
                    ->required(),

                TextInput::make('commission_percentage')
                    ->label('% Comisión')
                    ->numeric()
                    ->suffix('%')
                    ->required(),

                DatePicker::make('period_date')
                    ->label('Fecha Período')
                    ->required(),
            ]);
    }

    public function getBreadcrumbs(): array
    {
        $record = $this->getRecord();
        $simcard = $record->simcard;

        return [
            \App\Domain\Admin\Filament\Resources\SimcardResource::getUrl('index') => 'Cruces',
            \App\Domain\Admin\Filament\Resources\SimcardResource::getUrl('view', ['record' => $simcard]) => 'Ver',
            '#' => 'Condiciones de Venta',
            'current' => 'Editar',
        ];
    }
}
