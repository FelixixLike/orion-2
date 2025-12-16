<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Admin\Filament\Resources\RedemptionResource\Pages;

use App\Domain\Admin\Filament\Resources\RedemptionResource;
use App\Domain\Store\Enums\Municipality;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewRedemption extends ViewRecord
{
    protected static string $resource = RedemptionResource::class;

    public static function getNavigationLabel(): string
    {
        return 'Detalle de redencion';
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('back')
                ->label('Salir')
                ->color('gray')
                ->url(fn() => $this->getResource()::getUrl('index')),

            \Filament\Actions\Action::make('pdf')
                ->label('Descargar PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->url(fn($record) => route('admin.redemptions.pdf', $record))
                ->openUrlInNewTab(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        $record = $this->getRecord();
        $record->loadMissing('store.tenderer');

        return $schema->schema([
            Section::make('Resumen de liquidacion')
                ->schema([
                    TextEntry::make('redemptionProduct.name')->label('Producto'),
                    TextEntry::make('quantity')->label('Cantidad'),
                    TextEntry::make('total_value')->label('Valor total')->money('COP', true),
                    TextEntry::make('status')
                        ->label('Estado')
                        ->badge()
                        ->color(fn(string $state) => match ($state) {
                            'pending' => 'warning',
                            'approved' => 'info',
                            'delivered' => 'success',
                            'confirmed' => 'primary',
                            'rejected' => 'danger',
                            default => 'gray',
                        })
                        ->formatStateUsing(fn(string $state) => RedemptionResource::STATUSES[$state] ?? ucfirst($state)),
                    TextEntry::make('requested_at')->label('Solicitada')->dateTime('d/m/Y H:i'),
                    TextEntry::make('handledByUser.name')->label('Gestionada por')->default('N/D'),
                    TextEntry::make('notes')->label('Notas'),
                ])
                ->columns(3),

            Section::make('Redenciones asociadas')
                ->schema([
                    TextEntry::make('liquidation_id')
                        ->label('Liquidacion relacionada')
                        ->default(fn($record) => $record->liquidation_id ?: 'Sin asignar'),
                ]),

            Section::make('Detalle de la tienda')
                ->schema([
                    TextEntry::make('store.name')
                        ->label('Tienda')
                        ->badge()
                        ->color('primary'),
                    TextEntry::make('store.idpos')
                        ->label('IDPOS')
                        ->badge()
                        ->color('gray'),
                    TextEntry::make('store.municipality')
                        ->label('Municipio')
                        ->formatStateUsing(fn($state) => $state instanceof Municipality
                            ? $state->label()
                            : (Municipality::tryFrom($state)?->label() ?? $state)),
                    TextEntry::make('tenderer_name')
                        ->label('Solicitada por')
                        ->state(fn() => $record->store?->tenderer?->getFilamentName() ?? 'N/D')
                        ->columnSpanFull(),
                ])
                ->columns(3),
        ]);
    }
}
