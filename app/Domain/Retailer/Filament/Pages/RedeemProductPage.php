<?php

namespace App\Domain\Retailer\Filament\Pages;

use App\Domain\Retailer\Filament\Resources\RedemptionResource;
use App\Domain\Retailer\Support\ActiveStoreResolver;
use App\Domain\Retailer\Support\BalanceService;
use App\Domain\Store\Models\Redemption;
use App\Domain\Store\Models\RedemptionProduct;
use App\Domain\Store\Models\Store;
use App\Domain\Store\Enums\StoreStatus;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;

class RedeemProductPage extends Page
{
    protected static ?string $title = 'Redimir producto';

    protected static ?string $navigationLabel = 'Redimir producto';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $slug = 'redeem-product';

    protected string $view = 'filament.retailer.pages.redeem-product-page';

    public ?int $storeId = null;
    public array $stores = [];
    public ?int $productId = null;
    public ?array $product = null;
    public int $quantity = 1;
    public ?float $rechargeAmount = null;
    public float $estimatedTotal = 0.0;
    public float $storeBalance = 0.0;
    public string $notes = '';
    public ?int $monthlyLimit = null;
    public ?int $monthlyRemaining = null;

    private ?BalanceService $balanceService = null;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return 'redeem-product';
    }

    public function mount(): void
    {
        $this->balanceService = new BalanceService();
        $user = Auth::guard('retailer')->user();

        $this->stores = $this->resolveStores($user);
        $this->storeId = $this->resolveInitialStoreId($user);
        $this->storeBalance = $this->storeId
            ? (float) $this->getBalanceService()->getStoreBalance($this->storeId)
            : 0.0;

        $productId = request()->integer('product');
        if ($productId) {
            $this->assignProduct($productId);
        }

        if (!$this->product) {
            Notification::make()
                ->danger()
                ->title('Producto no disponible')
                ->body('Selecciona un producto desde el catケlogo para continuar.')
                ->send();

            $this->redirect(StoreCatalogPage::getUrl(panel: 'retailer'));
            return;
        }

        $this->updateEstimatedTotal();
    }

    public function updatedStoreId($value): void
    {
        $storeId = (int) $value;
        $validIds = collect($this->stores)
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();

        if (!in_array($storeId, $validIds, true)) {
            $this->storeId = null;
            $this->storeBalance = 0.0;
            $this->monthlyRemaining = null;
            return;
        }

        $this->storeId = $storeId;

        // VALIDACIÓN: Tienda Inactiva
        $storeModel = Store::find($storeId);
        if ($storeModel && $storeModel->status !== StoreStatus::ACTIVE) {
            Notification::make()
                ->danger()
                ->title('Tienda Inactiva')
                ->body('Esta tienda se encuentra inactiva y no puede realizar redenciones.')
                ->send();

            $this->storeId = null;
            $this->storeBalance = 0.0;
            $this->monthlyRemaining = null;
            return;
        }

        $user = Auth::guard('retailer')->user();
        if ($user && $this->storeId) {
            ActiveStoreResolver::setActiveStoreId($user, $this->storeId);
        }

        $this->storeBalance = (float) $this->getBalanceService()->getStoreBalance($this->storeId);
        $this->refreshMonthlyCounters();
    }

    public function updatedQuantity(): void
    {
        if ($this->quantity < 1) {
            $this->quantity = 1;
        }

        $this->updateEstimatedTotal();
    }

    public function updatedRechargeAmount(): void
    {
        $this->updateEstimatedTotal();
    }

    public function submit(): void
    {
        if (!$this->productId || !$this->product) {
            Notification::make()
                ->danger()
                ->title('Producto no disponible')
                ->body('Vuelve al catケlogo y selecciona un producto vケlido.')
                ->send();
            return;
        }

        if (!$this->storeId) {
            Notification::make()
                ->danger()
                ->title('Selecciona una tienda')
                ->body('Debes elegir la tienda desde la cual deseas redimir.')
                ->send();
            return;
        }

        $this->validate([
            'storeId' => ['required', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'rechargeAmount' => ['nullable', 'numeric', 'min:1000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $storeIds = collect($this->stores)
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();

        if (!in_array($this->storeId, $storeIds, true)) {
            Notification::make()
                ->danger()
                ->title('Tienda no autorizada')
                ->body('La tienda seleccionada no estケ asociada a tu usuario.')
                ->send();
            return;
        }

        $storeModel = Store::find($this->storeId);
        if ($storeModel && $storeModel->status !== StoreStatus::ACTIVE) {
            Notification::make()
                ->danger()
                ->title('Tienda Inactiva')
                ->body('Esta tienda se encuentra inactiva y no puede realizar redenciones.')
                ->send();
            return;
        }

        $product = RedemptionProduct::query()
            ->where('is_active', true)
            ->find($this->productId);

        if (!$product) {
            Notification::make()
                ->danger()
                ->title('Producto no disponible')
                ->body('El producto ya no estケ activo para redenciКn.')
                ->send();
            return;
        }

        $quantity = 1;
        $total = 0.0;

        if ($product->type === 'recharge') {
            $amount = (float) ($this->rechargeAmount ?? 0);
            if ($amount < 1000) {
                $this->addError('rechargeAmount', 'El valor mЯnimo de recarga es $1.000.');
                return;
            }

            if (!empty($product->max_value) && $amount > $product->max_value) {
                $this->addError('rechargeAmount', 'El valor excede el mЯximo permitido: ' . Number::currency($product->max_value, 'COP'));
                return;
            }

            $total = $amount;
        } else {
            $quantity = max(1, (int) $this->quantity);
            if ($product->stock !== null && $product->stock < $quantity) {
                $this->addError('quantity', "Stock insuficiente. Disponible: {$product->stock}");
                return;
            }

            if (!empty($product->monthly_store_limit) && $product->monthly_store_limit > 0) {
                $used = (int) Redemption::query()
                    ->where('store_id', $this->storeId)
                    ->where('redemption_product_id', $product->id)
                    ->whereBetween('requested_at', [now()->startOfMonth(), now()->endOfMonth()])
                    ->sum('quantity');

                $remaining = $product->monthly_store_limit - $used;
                if ($quantity > $remaining) {
                    $this->addError('quantity', "Superas el lМmite mensual ({$product->monthly_store_limit}). Restantes: " . max(0, $remaining));
                    $this->refreshMonthlyCounters($product);
                    return;
                }
            }

            $total = (float) $product->unit_value * $quantity;
        }

        if ($total <= 0) {
            Notification::make()
                ->danger()
                ->title('Valor no vКlido')
                ->body('Ingresa una cantidad o valor vКlido antes de redimir.')
                ->send();
            return;
        }

        $balance = (float) $this->getBalanceService()->getStoreBalance($this->storeId);
        $this->storeBalance = $balance;

        if ($total > $balance) {
            $field = $this->isRecharge() ? 'rechargeAmount' : 'quantity';
            $this->addError($field, 'Saldo insuficiente. Disponible: ' . Number::currency($balance, 'COP'));

            Notification::make()
                ->danger()
                ->title('Saldo insuficiente')
                ->body('No tienes saldo suficiente para completar esta redenciКn.')
                ->send();
            return;
        }

        Redemption::query()->create([
            'store_id' => $this->storeId,
            'redemption_product_id' => $product->id,
            'quantity' => $quantity,
            'total_value' => $total,
            'status' => 'pending',
            'requested_at' => now(),
            'notes' => $this->notes ?: null,
        ]);

        Notification::make()
            ->success()
            ->title('Solicitud enviada')
            ->body('Registramos tu redenciКn. Te notificaremos al actualizar el estado.')
            ->send();

        $this->redirect(RedemptionResource::getUrl('index', panel: 'retailer'));
    }

    private function resolveStores($user): array
    {
        if (!$user) {
            return [];
        }

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

        return $pivotStores
            ->concat($ownedStores)
            ->unique('id')
            ->sortBy('name')
            ->values()
            ->map(fn($store) => [
                'id' => (int) $store->id,
                'idpos' => $store->idpos,
                'name' => $store->name,
                'label' => ($store->idpos ? "{$store->idpos} - " : '') . ($store->name ?? 'Tienda'),
            ])
            ->toArray();
    }

    private function resolveInitialStoreId($user): ?int
    {
        if (empty($this->stores)) {
            return null;
        }

        $requested = request()->integer('store');
        $storeIds = collect($this->stores)->pluck('id')->map(fn($id) => (int) $id)->all();

        if ($requested && in_array((int) $requested, $storeIds, true)) {
            if ($user) {
                ActiveStoreResolver::setActiveStoreId($user, (int) $requested);
            }
            return (int) $requested;
        }

        if ($user) {
            $active = ActiveStoreResolver::getActiveStoreId($user);
            if ($active && in_array((int) $active, $storeIds, true)) {
                return (int) $active;
            }
        }

        return $storeIds[0] ?? null;
    }

    private function assignProduct(int $productId): void
    {
        $product = RedemptionProduct::query()
            ->where('is_active', true)
            ->find($productId);

        if (!$product) {
            $this->product = null;
            $this->productId = null;
            return;
        }

        $this->productId = $product->id;
        $this->product = [
            'id' => $product->id,
            'name' => $product->name,
            'type' => $product->type,
            'description' => $product->description ?? 'Producto disponible para redenciКn.',
            'unit_value' => (float) $product->unit_value,
            'stock' => $product->stock,
            'max_value' => $product->max_value,
            'monthly_store_limit' => $product->monthly_store_limit,
            'image_url' => $product->image_url,
        ];

        $this->quantity = 1;
        $this->rechargeAmount = null;
        $this->updateEstimatedTotal();
        $this->refreshMonthlyCounters($product);
    }

    private function updateEstimatedTotal(): void
    {
        if ($this->isRecharge()) {
            $this->estimatedTotal = (float) ($this->rechargeAmount ?? 0);
            return;
        }

        $unitValue = $this->product['unit_value'] ?? 0;
        $this->estimatedTotal = (float) $unitValue * max(1, (int) $this->quantity);
    }

    private function refreshMonthlyCounters(?RedemptionProduct $product = null): void
    {
        $product ??= ($this->productId
            ? RedemptionProduct::query()->find($this->productId)
            : null);

        if (!$product) {
            $this->monthlyLimit = null;
            $this->monthlyRemaining = null;
            return;
        }

        $limit = (int) ($product->monthly_store_limit ?? 0);
        $this->monthlyLimit = $limit > 0 ? $limit : null;

        if (!$this->storeId || $limit <= 0) {
            $this->monthlyRemaining = $this->monthlyLimit;
            return;
        }

        $used = (int) Redemption::query()
            ->where('store_id', $this->storeId)
            ->where('redemption_product_id', $product->id)
            ->whereBetween('requested_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('quantity');

        $this->monthlyRemaining = max(0, $limit - $used);
    }

    private function isRecharge(): bool
    {
        return ($this->product['type'] ?? null) === 'recharge';
    }

    private function getBalanceService(): BalanceService
    {
        if (!$this->balanceService) {
            $this->balanceService = new BalanceService();
        }

        return $this->balanceService;
    }
}
