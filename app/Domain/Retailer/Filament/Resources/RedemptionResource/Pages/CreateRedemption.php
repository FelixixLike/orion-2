<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Retailer\Filament\Resources\RedemptionResource\Pages;

use App\Domain\Retailer\Filament\Resources\RedemptionResource;
use App\Domain\Retailer\Support\ActiveStoreResolver;
use App\Domain\Retailer\Support\BalanceService;
use App\Domain\Store\Models\RedemptionProduct;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Number;

class CreateRedemption extends CreateRecord
{
    protected static string $resource = RedemptionResource::class;

    protected static ?string $title = 'Nueva redencion';

    protected static \UnitEnum|string|null $navigationGroup = 'Mis redenciones';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-plus';

    protected static ?string $navigationLabel = 'Nueva redencion';

    public static bool $shouldRegisterNavigation = false;

    private ?BalanceService $balanceService = null;

    public function mount(): void
    {
        parent::mount();

        $data = [];
        $user = Auth::guard('retailer')->user();
        $accessibleStoreIds = RedemptionResource::getUserStoreIds();

        $storeId = request()->integer('store', 0);
        if ($storeId > 0 && in_array($storeId, $accessibleStoreIds, true)) {
            $data = array_merge($data, $this->buildStorePrefillData($storeId));
        } elseif ($user && ! empty($accessibleStoreIds)) {
            $activeStoreId = ActiveStoreResolver::getActiveStoreId($user);
            if ($activeStoreId && in_array($activeStoreId, $accessibleStoreIds, true)) {
                $data = array_merge($data, $this->buildStorePrefillData($activeStoreId));
            }
        }

        $productId = request()->integer('product', 0);
        if ($productId > 0) {
            $product = RedemptionProduct::query()
                ->where('is_active', true)
                ->find($productId);

            if ($product) {
                $data = array_merge($data, $this->buildProductPrefillData($product));
            }
        }

        if (! empty($data)) {
            $this->form->fill($data);
        }
    }

    public static function canAccess(array $parameters = []): bool
    {
        $user = Auth::guard('retailer')->user();

        if (!$user) {
            return false;
        }

        $activeStoreId = ActiveStoreResolver::getActiveStoreId($user);
        if ($activeStoreId) {
            return true;
        }

        $fallbackStoreId = $user->stores()->pluck('stores.id')->first()
            ?? $user->ownedStores()->pluck('stores.id')->first();

        if ($fallbackStoreId) {
            ActiveStoreResolver::setActiveStoreId($user, (int) $fallbackStoreId);

            return true;
        }

        return false;
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()->label('Enviar solicitud de redencion'),
            $this->getCancelFormAction(),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = Auth::guard('retailer')->user();
        $storeId = $data['store_id'] ?? ActiveStoreResolver::getActiveStoreId($user);

        if (!$storeId) {
            throw ValidationException::withMessages([
                'store_id' => 'No se encontró una tienda válida. Selecciona una tienda.',
            ]);
        }

        $product = RedemptionProduct::query()
            ->where('is_active', true)
            ->find($data['redemption_product_id'] ?? null);

        if (!$product) {
            throw ValidationException::withMessages([
                'redemption_product_id' => 'El producto seleccionado no está disponible.',
            ]);
        }

        // Determine Total & Quantity based on Type
        if ($product->type === 'recharge') {
            $quantity = 1;
            $rechargeAmount = (float) ($data['recharge_amount'] ?? 0);
            $total = $rechargeAmount;

            if ($total < 1000) {
                throw ValidationException::withMessages([
                    'recharge_amount' => 'El valor mínimo de recarga es $1,000.',
                ]);
            }

            if ($product->max_value > 0 && $total > $product->max_value) {
                throw ValidationException::withMessages([
                    'recharge_amount' => 'El valor excede el máximo permitido: ' . \Illuminate\Support\Number::currency($product->max_value, 'COP'),
                ]);
            }
        } else {
            $quantity = (int) ($data['quantity'] ?? 1);
            if ($quantity < 1)
                $quantity = 1;

            $total = (float) $product->unit_value * $quantity;

            // Stock Check
            if ($product->stock !== null && $product->stock < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => "Stock insuficiente. Disponible: {$product->stock}",
                ]);
            }

            // Monthly Limit Check
            if ($product->monthly_store_limit > 0) {
                $used = \App\Domain\Store\Models\Redemption::query()
                    ->where('store_id', $storeId)
                    ->where('redemption_product_id', $product->id)
                    ->whereBetween('requested_at', [now()->startOfMonth(), now()->endOfMonth()])
                    ->sum('quantity');

                $remaining = $product->monthly_store_limit - $used;

                if ($quantity > $remaining) {
                    throw ValidationException::withMessages([
                        'quantity' => "Superas el límite mensual ({$product->monthly_store_limit}). Restantes: " . max(0, $remaining),
                    ]);
                }
            }
        }

        // Balance Check using BalanceService
        $balanceService = app(BalanceService::class);
        $balance = $balanceService->getStoreBalance($storeId);

        if ($total > $balance) {
            $formattedBalance = \Illuminate\Support\Number::currency($balance, 'COP');
            $errorKey = $product->type === 'recharge' ? 'recharge_amount' : 'quantity';

            throw ValidationException::withMessages([
                $errorKey => "Saldo insuficiente en la tienda. Saldo actual: {$formattedBalance}",
            ]);
        }

        // Prepare Data
        $data['store_id'] = $storeId;
        $data['quantity'] = $quantity;
        $data['status'] = 'pending';
        $data['total_value'] = $total;
        $data['requested_at'] = now();

        return $data;
    }

    private function buildStorePrefillData(int $storeId): array
    {
        $balance = $this->getBalanceService()->getStoreBalance($storeId);

        return [
            'store_id' => $storeId,
            'store_balance' => $balance,
            'store_balance_display' => Number::currency($balance, 'COP'),
        ];
    }

    private function buildProductPrefillData(RedemptionProduct $product): array
    {
        $data = [
            'redemption_product_id' => $product->id,
            'product_stock' => $product->stock,
            'product_max_value' => $product->max_value,
            'product_type' => $product->type,
            'unit_value' => $product->unit_value,
            'quantity' => 1,
            'recharge_amount' => null,
        ];

        $total = $product->type === 'recharge'
            ? 0
            : (float) $product->unit_value;

        $data['total_estimated_val'] = $total;
        $data['total_estimated_display'] = Number::currency($total, 'COP');

        return $data;
    }

    private function getBalanceService(): BalanceService
    {
        if (! $this->balanceService) {
            $this->balanceService = app(BalanceService::class);
        }

        return $this->balanceService;
    }
}
