<?php

namespace App\Domain\Retailer\Filament\Resources;

use App\Domain\Retailer\Filament\Resources\StoreResource\Pages;
use App\Domain\Store\Enums\Municipality;
use App\Domain\Store\Enums\StoreStatus;
use App\Domain\Store\Models\Store;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StoreResource extends Resource
{
    protected static ?string $model = Store::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-building-storefront';
    }

    public static function getNavigationLabel(): string
    {
        return 'Mi tienda';
    }

    public static function getModelLabel(): string
    {
        return 'Tienda';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Tienda';
    }

    public static function canViewAny(): bool
    {
        return auth('retailer')->check();
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Oculto del sidebar; se muestra en la página dedicada "Mi tienda".
        return false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $userId = auth('retailer')->id();

        if (! $userId) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()
            ->select([
                'stores.id',
                'stores.idpos',
                'stores.name',
                'stores.municipality',
                'stores.neighborhood',
                'stores.status',
                'stores.route_code',
                'stores.phone',
                'stores.email',
                'stores.address',
            ])
            ->join('store_user', 'stores.id', '=', 'store_user.store_id')
            ->where('store_user.user_id', $userId)
            ->distinct();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('idpos')
                    ->label('IDPOS')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Nombre')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('municipality')
                    ->label('Municipio')
                    ->formatStateUsing(fn ($state) => $state instanceof Municipality ? $state->label() : (Municipality::tryFrom($state)?->label() ?? $state))
                    ->sortable(),
                TextColumn::make('neighborhood')
                    ->label('Barrio')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof StoreStatus ? $state->label() : (StoreStatus::tryFrom($state)?->label() ?? $state))
                    ->colors(StoreStatus::badgeColors())
                    ->sortable(),
                TextColumn::make('route_code')
                    ->label('Ruta')
                    ->sortable(),
                TextColumn::make('phone')
                    ->label('Celular')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('email')
                    ->label('Correo')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(StoreStatus::options()),
                SelectFilter::make('municipality')
                    ->label('Municipio')
                    ->options(Municipality::options()),
            ])
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 25])
            ->defaultSort('idpos', 'asc')
            ->emptyStateHeading('Sin tienda asignada')
            ->emptyStateDescription('Tu usuario aun no tiene una tienda asociada. Contacta al administrador.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStores::route('/'),
        ];
    }
}
