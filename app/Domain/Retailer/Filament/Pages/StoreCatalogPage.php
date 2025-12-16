<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Retailer\Filament\Pages;

use App\Domain\Retailer\Filament\Pages\RedeemProductPage;
use App\Domain\Retailer\Support\ActiveStoreResolver;
use App\Domain\Retailer\Support\BalanceService;
use App\Domain\Store\Models\RedemptionProduct;
use App\Domain\Store\Models\Store;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class StoreCatalogPage extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Catálogo';

    protected static ?string $title = 'Catálogo de productos';

    protected string $view = 'filament.retailer.pages.store-catalog-page';

    public string $search = '';
    public string $type = 'all';
    public array $products = [];
    public array $types = [];
    public array $stores = [];
    public ?int $selectedStoreId = null;
    public float $availableBalance = 0.0;

    private ?BalanceService $balanceService = null;

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::guard('retailer')->check();
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return 'catalog';
    }

    public static function getNavigationSort(): ?int
    {
        return 4;
    }

    public function mount(): void
    {
        $user = Auth::guard('retailer')->user();

        $rawStores = collect();

        if ($user) {
            $baseSelect = [
                'stores.id',
                'stores.idpos',
                'stores.name',
            ];

            $pivotStores = $user->stores()
                ->select($baseSelect)
                ->get();

            $ownedStores = $user->ownedStores()
                ->select($baseSelect)
                ->get();

            $rawStores = $pivotStores
                ->concat($ownedStores)
                ->unique('id')
                ->sortBy('name')
                ->values();
        }

        $this->stores = $rawStores
            ->map(fn (Store $store) => [
                'id' => $store->id,
                'idpos' => $store->idpos,
                'name' => $store->name,
            ])
            ->toArray();

        $this->selectedStoreId = ActiveStoreResolver::getActiveStoreId($user) ?? ($this->stores[0]['id'] ?? null);

        $this->availableBalance = $this->selectedStoreId
            ? (float) $this->getBalanceService()->getStoreBalance($this->selectedStoreId)
            : 0.0;

        $this->types = RedemptionProduct::query()
            ->where('is_active', true)
            ->distinct()
            ->orderBy('type')
            ->pluck('type')
            ->filter()
            ->values()
            ->toArray();

        $this->loadProducts();
    }

    public function updatedSearch(): void
    {
        $this->loadProducts();
    }

    public function updatedSelectedStoreId($value): void
    {
        $storeId = (int) $value;
        $this->selectedStoreId = $storeId;

        $user = Auth::guard('retailer')->user();

        if ($user && $storeId) {
            ActiveStoreResolver::setActiveStoreId($user, $storeId);
        }

        $this->availableBalance = $storeId
            ? (float) $this->getBalanceService()->getStoreBalance($storeId)
            : 0.0;
    }

    public function updatedType(): void
    {
        $this->loadProducts();
    }

    public function redeem(int $productId, float $unitValue): void
    {
        if (! $this->selectedStoreId) {
            Notification::make()
                ->danger()
                ->title('Selecciona una tienda')
                ->body('Debes elegir desde qué tienda quieres redimir.')
                ->send();
            return;
        }

        // Recalcular siempre el saldo real de la tienda seleccionada
        $currentBalance = (float) $this->getBalanceService()->getStoreBalance($this->selectedStoreId);
        $this->availableBalance = $currentBalance;

        if ($currentBalance < $unitValue) {
            Notification::make()
                ->danger()
                ->title('Saldo insuficiente')
                ->body('Tu saldo disponible no alcanza para redimir este producto.')
                ->send();
            return;
        }

        $this->redirect(
            RedeemProductPage::getUrl(panel: 'retailer', parameters: [
                'product' => $productId,
                'store' => $this->selectedStoreId,
            ])
        );
    }

    private function getBalanceService(): BalanceService
    {
        if (! $this->balanceService) {
            $this->balanceService = new BalanceService();
        }

        return $this->balanceService;
    }

    private function loadProducts(): void
    {
        $hasStock = Schema::hasColumn('redemption_products', 'stock');
        $hasDescription = Schema::hasColumn('redemption_products', 'description');
        $hasImage = Schema::hasColumn('redemption_products', 'image_url') || Schema::hasColumn('redemption_products', 'image_path');

        $query = RedemptionProduct::query()
            ->where('is_active', true)
            ->when($this->type !== 'all', fn ($q) => $q->where('type', $this->type))
            ->when(filled($this->search), fn ($q) => $q->where('name', 'like', '%' . $this->search . '%'))
            ->orderBy('name');

        $columns = [
            'id',
            'name',
            'type',
            'unit_value',
        ];

        if ($hasDescription) {
            $columns[] = 'description';
        }

        if ($hasImage) {
            $columns[] = 'image_url';
            $columns[] = 'image_path';
        }

        if ($hasStock) {
            $columns[] = 'stock';
        }

        $this->products = $query->get($columns)->map(function ($product) use ($hasStock, $hasDescription, $hasImage) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'type' => $product->type,
                'unit_value' => $product->unit_value,
                'stock' => $hasStock ? ($product->stock ?? 0) : null,
                'description' => $hasDescription ? ($product->description ?? 'Producto disponible para redención') : 'Producto disponible para redención',
                'image_url' => $hasImage ? $product->image_url : null,
            ];
        })->toArray();
    }
}
