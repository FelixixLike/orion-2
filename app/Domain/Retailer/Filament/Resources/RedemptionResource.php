<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Retailer\Filament\Resources;

use App\Domain\Retailer\Filament\Resources\RedemptionResource\Pages;
use App\Domain\Retailer\Support\ActiveStoreResolver;
use App\Domain\Store\Models\Redemption;
use App\Domain\Store\Models\RedemptionProduct;
use App\Domain\Store\Models\Store;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;

class RedemptionResource extends Resource
{
    protected static ?string $model = Redemption::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-gift';
    }

    protected static ?string $navigationLabel = 'Redenciones';

    protected static ?int $navigationSort = 5;

    public const STATUSES = [
        'pending' => 'Pendiente',
        'approved' => 'Aprobada',
        'delivered' => 'Entregada',
        'rejected' => 'Rechazada',
        'cancelled' => 'Cancelada',
    ];

    // ... (rest of methods)

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->schema([
                Section::make('Información de la tienda')
                    ->columnSpan(1)
                    ->schema([
                        Select::make('store_id')
                            ->label('Seleccionar Tienda')
                            ->options(fn() => self::getUserStoreOptions())
                            ->required()
                            ->searchable()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $balance = \App\Domain\Store\Models\BalanceMovement::where('store_id', $state)->sum('amount');
                                    $set('store_balance', $balance);
                                    $set('store_balance_display', Number::currency($balance, 'COP'));
                                } else {
                                    $set('store_balance', 0);
                                    $set('store_balance_display', '-');
                                }
                            }),

                        Placeholder::make('store_balance_display')
                            ->label('Saldo Disponible')
                            ->content(fn($get) => $get('store_balance_display') ?? '-')
                            ->extraAttributes(['class' => 'text-xl font-bold text-primary-600']),

                        Hidden::make('store_balance')
                            ->default(0)
                            ->dehydrated(false),

                        Hidden::make('store_balance_display')
                            ->dehydrated(false),
                    ]),

                Section::make('Detalles del producto')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        Select::make('redemption_product_id')
                            ->label('Producto a redimir')
                            ->relationship('redemptionProduct', 'name', fn(Builder $query) => $query->where('is_active', true))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->columnSpan(2)
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                if ($state) {
                                    $product = RedemptionProduct::find($state);
                                    if ($product) {
                                        $set('product_stock', $product->stock);
                                        $set('product_max_value', $product->max_value);
                                        $set('product_type', $product->type);
                                        $set('unit_value', $product->unit_value);

                                        // Reset values
                                        $set('quantity', 1);
                                        $set('recharge_amount', null);
                                    }
                                }
                                self::updateEstimatedTotal($set, $get);
                            }),

                        Hidden::make('product_stock')->dehydrated(false),
                        Hidden::make('product_max_value')->dehydrated(false),
                        Hidden::make('product_type')->dehydrated(false),
                        Hidden::make('unit_value')->dehydrated(false),

                        TextInput::make('quantity')
                            ->label('Cantidad')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->required(fn(callable $get) => $get('product_type') !== 'recharge')
                            ->visible(fn(callable $get) => $get('product_type') !== 'recharge')
                            ->reactive()
                            ->maxValue(fn(callable $get) => $get('product_stock') ?? 9999)
                            ->hint(fn(callable $get) => $get('product_stock') !== null ? 'Disponible: ' . $get('product_stock') : '')
                            ->afterStateUpdated(fn(callable $set, $get) => self::updateEstimatedTotal($set, $get)),

                        TextInput::make('recharge_amount')
                            ->label('Valor de la recarga')
                            ->numeric()
                            ->prefix('$')
                            ->minValue(1000)
                            ->maxValue(fn(callable $get) => $get('product_max_value') ?? 99999999)
                            ->required(fn(callable $get) => $get('product_type') === 'recharge')
                            ->visible(fn(callable $get) => $get('product_type') === 'recharge')
                            ->reactive()
                            ->hint(fn(callable $get) => $get('product_max_value') ? 'Máximo: ' . Number::currency($get('product_max_value'), 'COP') : '')
                            ->afterStateUpdated(fn(callable $set, $get) => self::updateEstimatedTotal($set, $get)),

                        Placeholder::make('total_estimated')
                            ->label('Total A Redimir')
                            ->content(fn(callable $get) => $get('total_estimated_display') ?? '$ 0')
                            ->extraAttributes(['class' => 'text-2xl font-bold text-success-600'])
                            ->columnSpan(2),

                        Hidden::make('total_estimated_val')
                            ->dehydrated(false),

                        Hidden::make('total_estimated_display')
                            ->dehydrated(false),
                    ]),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('store.idpos')
                    ->label('ID_PDV')
                    ->sortable(),
                TextColumn::make('store.name')
                    ->label('Tienda')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('requested_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('redemptionProduct.name')
                    ->label('Producto')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('quantity')
                    ->label('Cantidad')
                    ->sortable(),
                TextColumn::make('total_value')
                    ->label('Total')
                    ->money('COP', true)
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn(string $state) => self::STATUSES[$state] ?? $state)
                    ->color(fn(string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'info',
                        'delivered' => 'success',
                        'cancelled', 'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(self::STATUSES),
                SelectFilter::make('store_id')
                    ->label('Tienda')
                    ->options(fn() => self::getUserStoreOptions()),
            ])
            ->actions([])
            ->bulkActions([])
            ->emptyStateHeading('Todavia no has realizado ninguna redencion.')
            ->emptyStateIcon('heroicon-o-rectangle-stack');
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::guard('retailer')->user();
        $storeIds = $user?->stores()->pluck('stores.id')->all() ?? [];

        if (empty($storeIds)) {
            return parent::getEloquentQuery()->whereRaw('1=0');
        }

        return parent::getEloquentQuery()
            ->whereIn('store_id', $storeIds)
            ->with(['redemptionProduct', 'store']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRedemptions::route('/'),
            'create' => Pages\CreateRedemption::route('/create'),
        ];
    }

    private static function calculateEstimatedTotal($get): float
    {
        $type = $get('product_type');

        if (!$type) {
            return 0;
        }

        if ($type === 'recharge') {
            return (float) ($get('recharge_amount') ?? 0);
        }

        $quantity = (int) ($get('quantity') ?? 0);
        $unitValue = (float) ($get('unit_value') ?? 0);

        return $quantity * $unitValue;
    }

    private static function updateEstimatedTotal(callable $set, $get): void
    {
        $total = self::calculateEstimatedTotal($get);
        $set('total_estimated_display', Number::currency($total, 'COP'));
        $set('total_estimated_val', $total);
    }

    private static function getUserAccessibleStores(): \Illuminate\Support\Collection
    {
        $user = Auth::guard('retailer')->user();

        if (! $user) {
            return collect();
        }

        $assignedStores = $user->stores()
            ->select(['stores.id', 'stores.idpos', 'stores.name'])
            ->get();

        $ownedStores = $user->ownedStores()
            ->select(['stores.id', 'stores.idpos', 'stores.name'])
            ->get();

        return $assignedStores
            ->merge($ownedStores)
            ->unique('id')
            ->values();
    }

    /**
     * @return array<int, string>
     */
    public static function getUserStoreOptions(): array
    {
        return self::getUserAccessibleStores()
            ->mapWithKeys(fn($store) => [
                $store->id => ($store->idpos ? "{$store->idpos} - " : '') . ($store->name ?? 'Tienda'),
            ])
            ->toArray();
    }

    /**
     * @return array<int>
     */
    public static function getUserStoreIds(): array
    {
        return self::getUserAccessibleStores()
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->values()
            ->all();
    }
}
