<?php

namespace App\Domain\Retailer\Filament\Resources;

use App\Domain\Retailer\Filament\Resources\LiquidationResource\Pages;
use App\Domain\Retailer\Support\ActiveStoreResolver;
use App\Domain\Store\Models\Liquidation;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class LiquidationResource extends Resource
{
    protected static ?string $model = Liquidation::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-wallet';
    }

    public static function getNavigationLabel(): string
    {
        return 'Balance';
    }

    public static function getModelLabel(): string
    {
        return 'Liquidacion';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Liquidaciones';
    }

    public static function getNavigationGroup(): ?string
    {
        return null;
    }

    public static function canViewAny(): bool
    {
        return auth('retailer')->check();
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Se oculta del sidebar porque el balance vive en la pagina BalancePage.
        return false;
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('period_year')
                    ->label('Anio')
                    ->sortable(),
                TextColumn::make('period_month')
                    ->label('Mes')
                    ->sortable(),
                TextColumn::make('net_amount')
                    ->label('Valor neto')
                    ->money('COP', true)
                    ->sortable(),
                BadgeColumn::make('status')
                    ->label('Estado')
                    ->colors([
                        'warning' => 'draft',
                        'success' => 'closed',
                    ])
                    ->formatStateUsing(fn (string $state) => $state === 'draft' ? 'Borrador' : 'Cerrada')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'draft' => 'Borrador',
                        'closed' => 'Cerrada',
                    ]),
            ])
            ->actions([
                Actions\ViewAction::make()
                    ->label('Ver detalle')
                    ->url(fn (Liquidation $record) => self::getUrl('view', ['record' => $record], panel: 'retailer')),
            ])
            ->bulkActions([])
            ->emptyStateHeading('Aun no tienes liquidaciones generadas para tu tienda.')
            ->emptyStateIcon('heroicon-o-clipboard-document');
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::guard('retailer')->user();
        $storeId = ActiveStoreResolver::getActiveStoreId($user);

        if (! $storeId) {
            return parent::getEloquentQuery()->whereRaw('1=0');
        }

        return parent::getEloquentQuery()
            ->where('store_id', $storeId);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLiquidations::route('/'),
            'view' => Pages\ViewLiquidation::route('/{record}'),
        ];
    }
}
