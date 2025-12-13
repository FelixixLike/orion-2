<?php

namespace App\Domain\Retailer\Filament\Pages;

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

class BalancePage extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Balance';

    protected static ?string $title = 'Resumen de saldo y movimientos';

    protected string $view = 'filament.retailer.pages.balance-page';

    public array $movements = [];

    public array $stores = [];

    public ?int $activeStoreId = null;

    public float $currentBalance = 0.0;

    public float $totalCredits = 0.0;

    public float $totalDebits = 0.0;

    public function mount(): void
    {
        $user = Auth::guard('retailer')->user();

        $rawStores = collect();

        if ($user) {
            $baseSelect = [
                'stores.id as id',
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

        $this->stores = $rawStores->toArray();

        $this->activeStoreId = ActiveStoreResolver::getActiveStoreId($user);

        $this->loadMovements();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::guard('retailer')->check();
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return 'balance';
    }

    /**
     * Cambia la tienda activa desde el front (chips) y recarga los movimientos.
     */
    public function setActiveStore(int $storeId): void
    {
        $this->activeStoreId = $storeId;

        $user = Auth::guard('retailer')->user();
        if ($user) {
            ActiveStoreResolver::setActiveStoreId($user, $storeId);
        }

        $this->loadMovements();
    }

    private function loadMovements(): void
    {
        $user = Auth::guard('retailer')->user();

        $storeId = $this->activeStoreId
            ?? ActiveStoreResolver::getActiveStoreId($user);

        if (! $storeId) {
            $this->movements = [];
            return;
        }

        $balanceService = app(BalanceService::class);
        $this->currentBalance = $balanceService->getStoreBalance($storeId);
        $this->totalCredits = 0.0;
        $this->totalDebits = 0.0;

        if (Schema::hasTable('balance_movements')) {
            try {
                $movementQuery = BalanceMovement::query()
                    ->where('store_id', $storeId)
                    ->where('status', 'active');

                if ($movementQuery->exists()) {
                    $credits = (float) (clone $movementQuery)
                        ->where('operation', 'liquidation')
                        ->sum('amount');

                    $debits = (float) (clone $movementQuery)
                        ->where('operation', 'redemption')
                        ->sum('amount');

                    $this->totalCredits = $credits;
                    $this->totalDebits = abs($debits);
                }
            } catch (QueryException $exception) {
                // Si la tabla no existe o falla la consulta, calcularemos los totales desde los movimientos cargados.
            }
        }

        $storeInfo = collect($this->stores)->firstWhere('id', $storeId) ?? [];

        $storeName = $storeInfo['name'] ?? null;
        $storeIdpos = $storeInfo['idpos'] ?? null;

        $operationLabels = [
            'liquidation' => 'Liquidacion',
            'redemption' => 'Redencion',
            'refund' => 'Devolucion',
            'adjustment' => 'Ajuste',
        ];

        // Preferir los movimientos reales registrados en balance_movements para reflejar el saldo exacto.
        if (Schema::hasTable('balance_movements')) {
            try {
                $movements = BalanceMovement::query()
                    ->where('store_id', $storeId)
                    ->where('status', 'active')
                    ->orderByDesc('movement_date')
                    ->orderByDesc('id')
                    ->limit(50)
                    ->get()
                    ->map(function (BalanceMovement $movement) use ($storeName, $storeIdpos, $operationLabels) {
                        $operation = $movement->operation ?? $movement->source_type ?? $movement->movement_type;
                        $amount = $this->signedAmount($movement);
                        $typeLabel = $operationLabels[$operation] ?? ucfirst($operation ?? 'Ajuste');

                        return [
                            'id' => $movement->id,
                            'date' => optional($movement->movement_date)->format('Y-m-d'),
                            'type' => $movement->movement_type,
                            'type_label' => $typeLabel,
                            'label' => $movement->description ?: $typeLabel,
                            'amount' => $amount,
                            'status' => $movement->status,
                            'status_label' => ucfirst($movement->status ?? ''),
                            'status_class' => $amount >= 0
                                ? 'bg-emerald-500/10 text-emerald-200'
                                : 'bg-red-500/10 text-red-200',
                            'url' => route('portal.movement.show', ['movement' => $movement->id]),
                            'export_url' => route('portal.movement.export', ['movement' => $movement->id]),
                            'store_name' => $storeName,
                            'idpos' => $storeIdpos,
                        ];
                    });

                if ($movements->isNotEmpty()) {
                    $this->movements = $movements
                        ->values()
                        ->map(function (array $item) {
                            $item['formatted_amount'] = Number::currency($item['amount'] ?? 0, 'COP');
                            return $item;
                        })
                        ->toArray();

                    $this->updateSummaryFromMovementsIfMissing();

                    return;
                }
            } catch (QueryException $exception) {
                // Si falla (p.ej. tabla no existe), usamos el fallback legacy.
            }
        }

        // Fallback legacy: solo liquidaciones cerradas y redenciones aprobadas/confirmadas/entregadas.
        $liquidations = Liquidation::query()
            ->where('store_id', $storeId)
            ->where('status', 'closed')
            ->select(['id', 'period_year', 'period_month', 'net_amount', 'status', 'created_at'])
            ->get()
            ->map(function (Liquidation $l) use ($storeName, $storeIdpos) {
                $period = sprintf('%02d/%s', $l->period_month, $l->period_year);

                return [
                    'date' => optional($l->created_at)->format('Y-m-d'),
                    'type' => 'credit',
                    'type_label' => 'Liquidacion',
                    'label' => 'Liquidacion ' . $period,
                    'amount' => (float) $l->net_amount,
                    'status' => $l->status,
                    'status_label' => 'Cerrada',
                    'status_class' => 'bg-emerald-500/10 text-emerald-200',
                    'url' => \App\Domain\Retailer\Filament\Resources\LiquidationResource::getUrl('view', ['record' => $l]),
                    'export_url' => null,
                    'store_name' => $storeName,
                    'idpos' => $storeIdpos,
                ];
            });

        $redemptions = Redemption::query()
            ->where('store_id', $storeId)
            ->whereIn('status', ['approved', 'confirmed', 'delivered'])
            ->with('redemptionProduct:id,name')
            ->select(['id', 'redemption_product_id', 'total_value', 'status', 'requested_at', 'store_id'])
            ->get()
            ->map(function (Redemption $r) use ($storeName, $storeIdpos) {
                $statusMap = [
                    'approved' => ['label' => 'Aprobada', 'class' => 'bg-indigo-500/10 text-indigo-200'],
                    'confirmed' => ['label' => 'Confirmada', 'class' => 'bg-blue-500/10 text-blue-200'],
                    'delivered' => ['label' => 'Entregada', 'class' => 'bg-emerald-500/10 text-emerald-200'],
                ];

                $status = $statusMap[$r->status] ?? [
                    'label' => ucfirst($r->status ?? 'Estado'),
                    'class' => 'bg-slate-900 text-slate-200',
                ];

                return [
                    'date' => optional($r->requested_at)->format('Y-m-d'),
                    'type' => 'debit',
                    'type_label' => 'Redencion',
                    'label' => 'Redencion ' . ($r->redemptionProduct?->name ?? 'Producto'),
                    'amount' => -1 * (float) $r->total_value,
                    'status' => $r->status,
                    'status_label' => $status['label'],
                    'status_class' => $status['class'],
                    'url' => \App\Domain\Retailer\Filament\Resources\RedemptionResource::getUrl('index', panel: 'retailer'),
                    'export_url' => null,
                    'store_name' => $storeName,
                    'idpos' => $storeIdpos,
                ];
            });

        $this->movements = $liquidations
            ->merge($redemptions)
            ->sortByDesc('date')
            ->values()
            ->map(function (array $item) {
                $item['formatted_amount'] = Number::currency($item['amount'] ?? 0, 'COP');
                return $item;
            })
            ->toArray();
    }

    private function updateSummaryFromMovementsIfMissing(): void
    {
        if (empty($this->movements)) {
            $this->totalCredits = 0.0;
            $this->totalDebits = 0.0;
            return;
        }

        if ($this->totalCredits !== 0.0 || $this->totalDebits !== 0.0) {
            return;
        }

        $credits = 0.0;
        $debits = 0.0;

        foreach ($this->movements as $movement) {
            $amount = (float) ($movement['amount'] ?? 0);

            if ($amount >= 0) {
                $credits += $amount;
            } else {
                $debits += abs($amount);
            }
        }

        $this->totalCredits = $credits;
        $this->totalDebits = $debits;
    }

    private function signedAmount(BalanceMovement $movement): float
    {
        $amount = abs((float) $movement->amount);

        return $movement->movement_type === 'debit' ? -1 * $amount : $amount;
    }
}
