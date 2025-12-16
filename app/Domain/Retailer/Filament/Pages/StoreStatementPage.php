<?php

namespace App\Domain\Retailer\Filament\Pages;

use App\Domain\Retailer\Support\ActiveStoreResolver;
use App\Domain\Retailer\Support\BalanceService;
use App\Domain\Store\Models\BalanceMovement;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;

class StoreStatementPage extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static ?string $navigationLabel = 'Estado de cuenta';

    protected static ?string $title = 'Estado de cuenta';

    protected static ?int $navigationSort = 40;

    protected string $view = 'filament.retailer.pages.store-statement-page';

    public ?int $storeId = null;
    public string $period = '';
    public array $stores = [];
    public array $periodOptions = [];
    public array $summary = [];
    public array $movements = [];

    private BalanceService $balanceService;

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::guard('retailer')->check();
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return 'store-statement';
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
            ->map(fn($store) => [
                'id' => $store->id,
                'label' => ($store->idpos ? "{$store->idpos} - " : '') . ($store->name ?? 'Tienda'),
            ])
            ->toArray();

        $this->storeId = request()->integer('store') ?: (ActiveStoreResolver::getActiveStoreId($user) ?? ($this->stores[0]['id'] ?? null));

        $this->loadPeriodOptions();

        $requestedPeriod = request()->get('period');
        if ($requestedPeriod && in_array($requestedPeriod, $this->periodOptions, true)) {
            $this->period = $requestedPeriod;
        } else {
            $this->period = $this->periodOptions[0] ?? '';
        }

        $this->loadStatement();
    }

    public function updatedStoreId($value): void
    {
        $this->storeId = (int) $value;

        $user = Auth::guard('retailer')->user();
        if ($user && $this->storeId) {
            ActiveStoreResolver::setActiveStoreId($user, $this->storeId);
        }

        $this->loadPeriodOptions();

        if (!empty($this->periodOptions) && !in_array($this->period, $this->periodOptions, true)) {
            $this->period = $this->periodOptions[0];
        }

        $this->loadStatement();
    }

    public function updatedPeriod(): void
    {
        $this->loadStatement();
    }

    public function export()
    {
        // Recalcular por seguridad antes de exportar
        $this->loadStatement();

        $filename = 'estado-cuenta-' . ($this->storeId ?? 'tienda') . '-' . ($this->period ?: 'periodo') . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Domain\Retailer\Exports\RetailerStoreStatementExport(
                $this->summary,
                $this->movements,
                $this->period
            ),
            $filename
        );
    }

    private function loadStatement(): void
    {
        if (!$this->storeId || !$this->period) {
            $this->summary = [];
            $this->movements = [];
            return;
        }

        $user = Auth::guard('retailer')->user();

        $validStoreIds = collect([]);

        if ($user) {
            $pivotIds = $user->stores()->pluck('stores.id');
            $ownedIds = $user->ownedStores()->pluck('stores.id');

            $validStoreIds = $pivotIds
                ->concat($ownedIds)
                ->map(fn($id) => (int) $id)
                ->unique()
                ->values();
        }

        if (!$validStoreIds->contains((int) $this->storeId)) {
            $this->summary = [];
            $this->movements = [];
            return;
        }

        [$start, $end] = $this->periodRange($this->period);

        $baseQuery = BalanceMovement::query()
            ->where('store_id', $this->storeId)
            ->where('status', 'active');

        $storeLabel = collect($this->stores)->firstWhere('id', $this->storeId)['label'] ?? 'Tienda';

        $initialBalance = (clone $baseQuery)
            ->where('movement_date', '<', $start)
            ->orderBy('movement_date')
            ->orderBy('id')
            ->get()
            ->map(fn(BalanceMovement $movement) => $this->signedAmount($movement))
            ->sum();

        $periodMovements = (clone $baseQuery)
            ->whereBetween('movement_date', [$start, $end])
            ->orderBy('movement_date')
            ->orderBy('id')
            ->get();

        $runningBalance = $initialBalance;
        $credits = 0.0;
        $debits = 0.0;
        $adjustments = 0.0;

        $this->movements = $periodMovements
            ->map(function (BalanceMovement $movement) use (&$runningBalance, &$credits, &$debits, &$adjustments, $storeLabel) {
                $delta = $this->signedAmount($movement);

                if ($delta >= 0) {
                    $credits += $delta;
                } else {
                    $debits += abs($delta);
                }

                if (($movement->operation ?? $movement->source_type) === 'adjustment') {
                    $adjustments += $delta;
                }

                $runningBalance += $delta;

                return [
                    'date' => optional($movement->movement_date)->format('Y-m-d'),
                    'type_label' => $this->movementLabel($movement),
                    'description' => $movement->description,
                    'amount' => $delta,
                    'store_label' => $storeLabel,
                    'balance' => $runningBalance,
                ];
            })
            ->values()
            ->toArray();

        $this->summary = [
            'initial' => $initialBalance,
            'credits' => $credits,
            'debits' => $debits,
            'adjustments' => $adjustments,
            'final' => $runningBalance,
            'store_label' => $storeLabel,
        ];
    }

    private function loadPeriodOptions(): void
    {
        if (!$this->storeId) {
            $this->periodOptions = [];
            return;
        }

        $periods = BalanceMovement::query()
            ->where('store_id', $this->storeId)
            ->where('status', 'active')
            ->whereNotNull('movement_date')
            ->selectRaw("to_char(movement_date, 'YYYY-MM') as period")
            ->distinct()
            ->orderByDesc('period')
            ->pluck('period')
            ->filter()
            ->values()
            ->toArray();

        $this->periodOptions = $periods;

        if (empty($this->periodOptions)) {
            $this->period = '';
        }
    }

    /**
     * @return array{0:\Carbon\CarbonImmutable,1:\Carbon\CarbonImmutable}
     */
    private function periodRange(string $period): array
    {
        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth()->toImmutable();
        $end = $start->endOfMonth();

        return [$start, $end];
    }

    private function signedAmount(BalanceMovement $movement): float
    {
        $amount = abs((float) $movement->amount);

        return $movement->movement_type === 'debit' ? -1 * $amount : $amount;
    }

    private function movementLabel(BalanceMovement $movement): string
    {
        return match ($movement->operation ?? $movement->source_type) {
            'liquidation' => 'Liquidacion',
            'redemption' => 'Redencion',
            'refund' => 'Devolucion',
            'adjustment' => 'Ajuste',
            default => ucfirst($movement->operation ?? $movement->source_type ?? 'Movimiento'),
        };
    }
}
