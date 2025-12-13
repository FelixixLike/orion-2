<?php

namespace App\Domain\Retailer\Filament\Resources\LiquidationResource\Pages;

use App\Domain\Retailer\Filament\Resources\LiquidationResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewLiquidation extends ViewRecord
{
    protected static string $resource = LiquidationResource::class;

    protected static ?string $title = 'Detalle de liquidacion';

    public function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Resumen')
                ->schema([
                    TextEntry::make('period_month')
                        ->label('Periodo')
                        ->formatStateUsing(function ($record) {
                            return $record?->period_month && $record?->period_year
                                ? sprintf('%02d/%s', $record->period_month, $record->period_year)
                                : 'N/D';
                        }),
                    TextEntry::make('net_amount')
                        ->label('Valor neto')
                        ->money('COP', true),
                    TextEntry::make('status')
                        ->label('Estado')
                        ->badge()
                        ->color(fn (string $state) => $state === 'draft' ? 'warning' : 'success')
                        ->formatStateUsing(fn (string $state) => $state === 'draft' ? 'Borrador' : 'Cerrada'),
                    TextEntry::make('clarifications')
                        ->label('Notas')
                        ->default('Sin observaciones'),
                ])
                ->columns(2),

            Section::make('Tienda')
                ->schema([
                    TextEntry::make('store.idpos')->label('IDPOS'),
                    TextEntry::make('store.name')->label('Nombre'),
                    TextEntry::make('store.route_code')->label('Ruta'),
                    TextEntry::make('store.municipality')->label('Municipio'),
                ])
                ->columns(2),
        ]);
    }
}
