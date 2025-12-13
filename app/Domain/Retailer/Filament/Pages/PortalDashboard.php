<?php

namespace App\Domain\Retailer\Filament\Pages;

use App\Domain\Retailer\Filament\Resources\RedemptionResource;
use App\Domain\Retailer\Support\ActiveStoreResolver;
use App\Domain\Retailer\Support\BalanceService;
use App\Domain\Store\Models\BalanceMovement;
use App\Domain\Store\Models\Liquidation;
use App\Domain\Store\Models\Redemption;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Number;

class PortalDashboard extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-home';

    protected string $view = 'filament.retailer.pages.portal-dashboard';

    protected static ?string $navigationLabel = 'Inicio';

    protected static ?string $title = 'Resumen';

    protected static \UnitEnum|string|null $navigationGroup = null;

    public ?array $summary = null;
    public array $recentRedemptions = [];
    public array $movements = [];
    public array $stores = [];
    public ?int $activeStoreId = null;
    public float $userTotalBalance = 0.0;
    private BalanceService $balanceService;

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public static function getSlug(?Panel $panel = null): string
    {
        // Slug no debe ser "/" para que el nombre de ruta se genere correctamente.
        return 'dashboard';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public function mount(): void
    {
        $this->balanceService = new BalanceService();
        $user = Auth::guard('retailer')->user();
        $this->userTotalBalance = $user ? (float) $this->balanceService->getUserTotalBalance($user) : 0.0;

        $rawStores = collect();

        if ($user) {
            $baseSelect = [
                'stores.id',
                'stores.idpos',
                'stores.name',
                'stores.route_code',
                'stores.circuit_code',
                'stores.municipality',
                'stores.neighborhood',
                'stores.address',
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
            ->map(function ($store) {
                return array_merge(
                    $store->toArray(),
                    ['balance' => (float) $this->balanceService->getStoreBalance($store->id)]
                );
            })
            ->toArray();

        $storeIds = collect($this->stores)->pluck('id')->all();
        $this->activeStoreId = ActiveStoreResolver::getActiveStoreId($user);

        $latestLiquidation = $this->getLatestLiquidation($storeIds);

        $this->summary = [
            'user_total_balance' => $this->userTotalBalance,
            'latest_period' => $latestLiquidation
                ? sprintf('%02d/%s', $latestLiquidation->period_month, $latestLiquidation->period_year)
                : null,
            'latest_amount' => $latestLiquidation?->net_amount ?? null,
            'latest_status' => $latestLiquidation?->status,
        ];

        $this->recentRedemptions = $this->getRecentRedemptions($storeIds);
        $this->movements = $this->getRecentMovements($storeIds);
    }

    private function getLatestLiquidation(array $storeIds): ?Liquidation
    {
        if (empty($storeIds)) {
            return null;
        }

        return Liquidation::query()
            ->whereIn('store_id', $storeIds)
            ->where('status', 'closed')
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->orderByDesc('created_at')
            ->first();
    }

    private function getRecentRedemptions(array $storeIds): array
    {
        if (empty($storeIds)) {
            return [];
        }

        return Redemption::query()
            ->whereIn('store_id', $storeIds)
            ->with(['redemptionProduct', 'store'])
            ->latest('requested_at')
            ->limit(5)
            ->get()
            ->map(function (Redemption $redemption) {
                $status = RedemptionResource::STATUSES[$redemption->status] ?? $redemption->status;

                return [
                    'date' => optional($redemption->requested_at)->format('Y-m-d'),
                    'product' => $redemption->redemptionProduct?->name ?? 'Producto',
                    'total' => $redemption->total_value,
                    'status' => $status,
                    'store' => $redemption->store?->name,
                    'idpos' => $redemption->store?->idpos,
                ];
            })
            ->toArray();
    }

    private function getRecentMovements(array $storeIds): array
    {
        $operationLabels = [
            'liquidation' => 'Liquidacion',
            'redemption' => 'Redencion',
            'refund' => 'Devolucion',
            'adjustment' => 'Ajuste',
        ];

        if (empty($storeIds)) {
            return [];
        }

        if (!Schema::hasTable('balance_movements')) {
            return $this->getLegacyRecentMovements($storeIds);
        }

        try {
            $movements = BalanceMovement::query()
                ->whereIn('store_id', $storeIds)
                ->where('status', 'active')
                ->with('store')
                ->orderByDesc('movement_date')
                ->orderByDesc('id')
                ->limit(10)
                ->get()
                ->map(function (BalanceMovement $movement) use ($operationLabels) {
                    $operation = $movement->operation ?? $movement->source_type ?? $movement->movement_type;
                    $amount = $this->normalizeMovementAmount($movement);

                    return [
                        'date' => optional($movement->movement_date)->format('Y-m-d'),
                        'type' => $operationLabels[$operation] ?? ucfirst($operation ?? 'Movimiento'),
                        'detail' => $movement->description,
                        'amount' => $amount,
                        'status' => $movement->status,
                        'store_name' => $movement->store->name ?? null,
                        'store_idpos' => $movement->store->idpos ?? null,
                        'movement_type' => $movement->movement_type,
                        'operation' => $operation,
                    ];
                });
        } catch (QueryException $exception) {
            // Si la tabla no existe en el entorno actual, usamos el fallback legacy.
            return $this->getLegacyRecentMovements($storeIds);
        }

        if ($movements->isNotEmpty()) {
            return $movements->toArray();
        }

        // TODO: eliminar fallback cuando todos los movimientos se registren en balance_movements.
        return $this->getLegacyRecentMovements($storeIds);
    }

    private function normalizeMovementAmount(BalanceMovement $movement): float
    {
        $amount = abs((float) $movement->amount);

        return $movement->movement_type === 'debit' ? -1 * $amount : $amount;
    }

    private function getLegacyRecentMovements(array $storeIds): array
    {
        if (empty($storeIds)) {
            return [];
        }

        $liquidations = Liquidation::query()
            ->whereIn('store_id', $storeIds)
            ->where('status', 'closed')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(function (Liquidation $liquidation) {
                $store = $liquidation->store;
                $period = sprintf('%02d/%s', $liquidation->period_month, $liquidation->period_year);
                return [
                    'date' => optional($liquidation->created_at)->format('Y-m-d'),
                    'type' => 'Liquidacion',
                    'detail' => "Liquidacion {$period}",
                    'amount' => $liquidation->net_amount,
                    'store_name' => $store?->name,
                    'store_idpos' => $store?->idpos,
                    'movement_type' => 'credit',
                    'operation' => 'liquidation',
                ];
            });

        $redemptions = Redemption::query()
            ->whereIn('store_id', $storeIds)
            ->whereIn('status', ['approved', 'confirmed', 'delivered'])
            ->with('redemptionProduct')
            ->orderByDesc('requested_at')
            ->limit(10)
            ->get()
            ->map(function (Redemption $redemption) {
                $store = $redemption->store;
                return [
                    'date' => optional($redemption->requested_at)->format('Y-m-d'),
                    'type' => 'Redencion',
                    'detail' => 'Redencion ' . ($redemption->redemptionProduct?->name ?? ''),
                    'amount' => -1 * ((float) $redemption->total_value),
                    'store_name' => $store?->name,
                    'store_idpos' => $store?->idpos,
                    'movement_type' => 'debit',
                    'operation' => 'redemption',
                ];
            });

        return $liquidations
            ->merge($redemptions)
            ->sortByDesc('date')
            ->take(10)
            ->values()
            ->toArray();
    }
}
