<?php

namespace App\Domain\Retailer\Filament\Resources\LiquidationResource\Pages;

use App\Domain\Retailer\Filament\Resources\LiquidationResource;
use Filament\Resources\Pages\ListRecords;

class ListLiquidations extends ListRecords
{
    protected static string $resource = LiquidationResource::class;

    protected static ?string $title = 'Mis liquidaciones';

    protected function getTableHeading(): ?string
    {
        return 'Mis liquidaciones';
    }

    protected function getTableColumns(): array
    {
        return [
            \Filament\Tables\Columns\TextColumn::make('period_month')
                ->label('Periodo')
                ->formatStateUsing(fn ($record) => $record?->period_month && $record?->period_year
                    ? \Illuminate\Support\Carbon::create($record->period_year, $record->period_month, 1)->isoFormat('MMMM YYYY')
                    : 'N/D')
                ->sortable(),
            \Filament\Tables\Columns\TextColumn::make('net_amount')
                ->label('Valor neto')
                ->money('COP', true)
                ->sortable(),
            \Filament\Tables\Columns\TextColumn::make('status')
                ->label('Estado')
                ->badge()
                ->colors([
                    'warning' => 'draft',
                    'success' => 'closed',
                ])
                ->formatStateUsing(fn (string $state) => $state === 'draft' ? 'Borrador' : 'Cerrada')
                ->sortable(),
            \Filament\Tables\Columns\TextColumn::make('created_at')
                ->label('Creada')
                ->dateTime('d/m/Y H:i')
                ->sortable(),
        ];
    }

    protected function getEmptyStateHeading(): ?string
    {
        return 'Aun no tienes liquidaciones generadas para tu tienda.';
    }

    protected function getEmptyStateDescription(): ?string
    {
        return 'Cuando cierre tu primera liquidacion podras revisarla aqui.';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
