<?php

namespace App\Domain\Retailer\Filament\Resources\RedemptionResource\Pages;

use App\Domain\Retailer\Filament\Resources\RedemptionResource;
use App\Domain\Retailer\Filament\Pages\StoreCatalogPage;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Columns\TextColumn;

class ListRedemptions extends ListRecords
{
    protected static string $resource = RedemptionResource::class;

    protected static ?string $title = 'Historial de redenciones';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('catalog')
                ->label('Catálogo')
                ->icon('heroicon-o-rectangle-stack')
                ->color('primary')
                ->url(fn (): string => StoreCatalogPage::getUrl(panel: 'retailer')),
        ];
    }

    protected function getTableHeading(): ?string
    {
        return 'Historial de redenciones';
    }

    public function getBreadcrumbs(): array
    {
        // Ocultamos breadcrumb redundante ("Redemptions > Listado") en el portal tendero.
        return [];
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('requested_at')
                ->label('Fecha')
                ->dateTime('d/m/Y H:i')
                ->sortable(),
            TextColumn::make('redemptionProduct.name')
                ->label('Producto')
                ->sortable()
                ->searchable(),
            TextColumn::make('quantity')
                ->label('Cantidad')
                ->numeric()
                ->sortable(),
            TextColumn::make('total_value')
                ->label('Total')
                ->money('COP', true)
                ->sortable(),
            TextColumn::make('status')
                ->label('Estado')
                ->badge()
                ->colors([
                    'warning' => 'pending',
                    'info' => 'approved',
                    'success' => 'delivered',
                    'primary' => 'confirmed',
                    'danger' => 'rejected',
                ])
                ->formatStateUsing(fn (string $state) => RedemptionResource::STATUSES[$state] ?? ucfirst($state))
                ->sortable(),
        ];
    }

    protected function getEmptyStateHeading(): ?string
    {
        return 'Aun no has realizado ninguna redencion.';
    }

    protected function getEmptyStateDescription(): ?string
    {
        return 'Cuando envies tu primera solicitud, veras el historial en este listado.';
    }

    // No mostramos acción de "Nueva redencion" aquí;
    // las solicitudes se inician desde el catálogo.
}
