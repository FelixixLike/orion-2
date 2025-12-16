<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Retailer\Filament\Pages;

use App\Domain\Retailer\Support\ActiveStoreResolver;
use App\Domain\Retailer\Support\BalanceService;
use App\Domain\Store\Models\RedemptionProduct;
use App\Domain\Store\Models\Store;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Facades\Auth;

class StoresPage extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Mis tiendas';

    protected static ?string $title = 'Mis tiendas';

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'my-stores';

    protected string $view = 'filament.retailer.pages.stores-page';

    public array $stores = [];
    public ?array $store = null;
    public ?int $activeStoreId = null;
    public array $routes = [];
    public array $municipalities = [];
    public ?string $filterRoute = null;
    public ?string $filterMunicipality = null;

    private BalanceService $balanceService;

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return 'my-stores';
    }

    public function mount(): void
    {
        $this->balanceService = new BalanceService();
        $user = Auth::guard('retailer')->user();

        $rawStores = collect();

        if ($user) {
            $baseSelect = [
                'stores.id',
                'stores.idpos',
                'stores.name',
                'stores.address',
                'stores.municipality',
                'stores.neighborhood',
                'stores.route_code',
                'stores.circuit_code',
                'stores.category',
                'stores.phone',
                'stores.email',
                'stores.status',
            ];

            // Tiendas asignadas por pivot store_user
            $pivotStores = $user->stores()
                ->select($baseSelect)
                ->get();

            // Tiendas donde es tendero principal (stores.user_id)
            $ownedStores = $user->ownedStores()
                ->select($baseSelect)
                ->get();

            $rawStores = $pivotStores
                ->concat($ownedStores)
                ->unique('id')
                ->sortBy('name')
                ->values();
        }

        $this->routes = $rawStores->pluck('route_code')->filter()->unique()->sort()->values()->toArray();
        $this->municipalities = $rawStores->pluck('municipality')->filter()->unique()->sort()->values()->toArray();

        $this->stores = $rawStores
            ->map(function (Store $store) {
                return array_merge(
                    $store->toArray(),
                    [
                        'balance' => (float) $this->balanceService->getStoreBalance($store->id),
                    ]
                );
            })
            ->toArray();

        $this->activeStoreId = ActiveStoreResolver::getActiveStoreId($user) ?? ($this->stores[0]['id'] ?? null);

        if ($this->activeStoreId) {
            $this->store = Store::query()
                ->select([
                    'id',
                    'idpos',
                    'name',
                    'route_code',
                    'address',
                    'municipality',
                    'neighborhood',
                    'phone',
                    'email',
                ])
                ->find($this->activeStoreId)?->toArray();

            if ($this->store) {
                $this->store['balance'] = (float) $this->balanceService->getStoreBalance($this->store['id']);
            }
        }

    }
}
