<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Admin\Filament\Pages;

use App\Domain\Retailer\Support\BalanceService;
use App\Domain\Store\Models\BalanceMovement;
use App\Domain\Store\Models\Liquidation;
use App\Domain\Store\Models\Redemption;
use App\Domain\Store\Models\Store;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;

class StoreStatementPage extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static ?string $navigationLabel = 'Estado de cuenta';

    protected static ?string $title = 'Estado de cuenta por tienda';

    protected static \UnitEnum|string|null $navigationGroup = 'Tiendas';

    protected static ?int $navigationSort = 31;

    protected string $view = 'filament.admin.stores.store-statement-page';

    public ?int $storeId = null;
    public string $period = '';
    public string $searchStore = ''; // Filtro de búsqueda manual

    public array $stores = [];
    public array $periodOptions = [];
    public array $summary = [];
    public array $movements = [];

    // ...

    public function updatedSearchStore(): void
    {
        $this->loadStores();
    }

    // Método auxiliar para no duplicar lógica de carga
    private function loadStores(): void
    {
        $query = Store::query()
            ->select(['id', 'idpos', 'name'])
            ->orderBy('name');

        if ($this->searchStore) {
            $query->where(function ($q) {
                $q->where('name', 'ilike', "%{$this->searchStore}%")
                    ->orWhere('idpos', 'ilike', "%{$this->searchStore}%");
            });
        }

        $this->stores = $query->limit(50) // Limitar resultados para mejorar rendimiento
            ->get()
            ->map(fn(Store $store) => [
                'id' => $store->id,
                'label' => ($store->idpos ? "{$store->idpos} - " : '') . ($store->name ?? 'Tienda'),
            ])
            ->toArray();
    }

    private ?BalanceService $balanceService = null;

    protected $listeners = ['refreshStatement' => 'loadStatement'];

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::guard('admin')->user();
        return $user?->hasAnyRole(['super_admin', 'administrator'], 'admin') ?? false;
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return 'stores/statement';
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('export')
                ->label('Exportar estado de cuenta')
                ->icon('heroicon-o-arrow-down-tray')
                ->action('export')
                ->visible(fn() => !empty($this->movements)),
        ];
    }

    public function mount(): void
    {
        $this->balanceService = new BalanceService();
        $this->loadStores();

        $this->storeId = request()->integer('store') ?: ($this->stores[0]['id'] ?? null);
        $this->loadPeriodOptions();

        $requestedPeriod = request()->get('period');
        if ($requestedPeriod && in_array($requestedPeriod, $this->periodOptions, true)) {
            $this->period = $requestedPeriod;
        } else {
            $this->period = $this->periodOptions[0] ?? '';
        }

        $this->loadStatement();
    }


    public function updatedStoreId(): void
    {
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
        $filename = 'estado-cuenta-' . ($this->storeId ?? 'tienda') . '-' . ($this->period ?: 'periodo') . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Domain\Admin\Exports\StoreStatementExport($this->movements), $filename);
    }

    public function loadStatement(): void
    {
        // Asegura que el servicio esté inicializado también en requests Livewire posteriores.
        if ($this->balanceService === null) {
            $this->balanceService = new BalanceService();
        }

        if (!$this->storeId || !$this->period) {
            $this->summary = [];
            $this->movements = [];
            return;
        }

        $store = Store::query()->with(['users', 'tenderer'])->find($this->storeId);
        $storeLabel = collect($this->stores)->firstWhere('id', $this->storeId)['label'] ?? 'Tienda';

        [$start, $end] = $this->periodRange($this->period);

        $baseQuery = BalanceMovement::query()
            ->where('store_id', $this->storeId)
            ->where('status', 'active');

        $periodQuery = (clone $baseQuery)->whereBetween('movement_date', [$start, $end]);

        if (!(clone $periodQuery)->exists()) {
            $this->buildLegacyStatement($start, $end, $store, $storeLabel);
            return;
        }

        $initialBalance = (clone $baseQuery)
            ->where('movement_date', '<', $start)
            ->orderBy('movement_date')
            ->orderBy('id')
            ->get()
            ->map(fn(BalanceMovement $movement) => $this->signedAmount($movement))
            ->sum();

        $periodMovements = (clone $periodQuery)
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
                    'source_label' => ucfirst($movement->operation ?? $movement->source_type ?? ''),
                    'balance' => $runningBalance,
                    'store_label' => $storeLabel,
                ];
            })
            ->values()
            ->toArray();

        $this->buildSummary($store, $storeLabel, $initialBalance, $credits, $debits, $adjustments, $runningBalance);
    }


    private function loadPeriodOptions(): void
    {
        if (!$this->storeId) {
            $this->periodOptions = [];
            return;
        }

        $balancePeriods = BalanceMovement::query()
            ->where('store_id', $this->storeId)
            ->where('status', 'active')
            ->whereNotNull('movement_date')
            ->selectRaw("to_char(movement_date, 'YYYY-MM') as period")
            ->distinct()
            ->orderByDesc('period')
            ->pluck('period')
            ->filter(fn($value) => !empty($value))
            ->values()
            ->toArray();

        $legacyPeriods = $this->collectLegacyPeriods($this->storeId);

        $this->periodOptions = collect($balancePeriods)
            ->merge($legacyPeriods)
            ->filter(fn($value) => !empty($value))
            ->unique()
            ->sortDesc()
            ->values()
            ->toArray();

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

    private function buildSummary(?Store $store, string $storeLabel, float $initialBalance, float $credits, float $debits, float $adjustments, float $finalBalance): void
    {
        $tender = $store?->users?->first() ?? $store?->tenderer;
        $tenderBalance = $tender ? (float) $this->balanceService->getUserTotalBalance($tender) : 0.0;
        $difference = $tenderBalance - $finalBalance;
        $balanceDenominator = abs((float) $finalBalance);
        $rate = $balanceDenominator > 0.0 ? abs($difference) / $balanceDenominator : null;

        $this->summary = [
            'initial' => $initialBalance,
            'credits' => $credits,
            'debits' => $debits,
            'adjustments' => $adjustments,
            'final' => $finalBalance,
            'tender_balance' => $tenderBalance,
            'difference' => $difference,
            'warn' => abs($difference) > 1 || ($rate !== null && $rate > 0.01),
            'store_label' => $storeLabel,
        ];
    }

    private function collectLegacyPeriods(int $storeId): array
    {
        $liquidationPeriods = Liquidation::query()
            ->where('store_id', $storeId)
            ->where('status', 'closed')
            ->select(['period_year', 'period_month'])
            ->distinct()
            ->get()
            ->map(function (Liquidation $liquidation) {
                $year = (int) $liquidation->period_year;
                $month = (int) $liquidation->period_month;

                return sprintf('%04d-%02d', $year, $month);
            })
            ->filter();

        $redemptionPeriods = Redemption::query()
            ->where('store_id', $storeId)
            ->whereIn('status', ['approved', 'confirmed', 'delivered'])
            ->whereNotNull('requested_at')
            ->selectRaw("date_trunc('month', requested_at) as period_month")
            ->distinct()
            ->pluck('period_month')
            ->map(fn($value) => $value ? Carbon::parse($value)->format('Y-m') : null)
            ->filter();

        return $liquidationPeriods
            ->merge($redemptionPeriods)
            ->filter()
            ->unique()
            ->sortDesc()
            ->values()
            ->toArray();
    }

    /**
     * Fallback para mostrar periodos construidos desde liquidaciones/redenciones
     * cuando balance_movements aun no tiene registros de la tienda.
     */
    private function buildLegacyStatement(\Carbon\CarbonImmutable $start, \Carbon\CarbonImmutable $end, ?Store $store, string $storeLabel): void
    {
        $liquidationBase = Liquidation::query()
            ->where('store_id', $this->storeId)
            ->where('status', 'closed')
            ->whereNotNull('created_at');

        $redemptionBase = Redemption::query()
            ->where('store_id', $this->storeId)
            ->whereIn('status', ['approved', 'confirmed', 'delivered'])
            ->whereNotNull('requested_at');

        $initialCredits = (clone $liquidationBase)
            ->where('created_at', '<', $start)
            ->sum('net_amount');

        $initialDebits = (clone $redemptionBase)
            ->where('requested_at', '<', $start)
            ->sum('total_value');

        $initialBalance = (float) $initialCredits - (float) $initialDebits;

        $periodLiquidations = (clone $liquidationBase)
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'period_year', 'period_month', 'net_amount', 'created_at']);

        $periodRedemptions = (clone $redemptionBase)
            ->whereBetween('requested_at', [$start, $end])
            ->orderBy('requested_at')
            ->orderBy('id')
            ->with('redemptionProduct:id,name')
            ->get(['id', 'redemption_product_id', 'requested_at', 'total_value', 'status', 'created_at']);

        $legacyMovements = collect();

        foreach ($periodLiquidations as $liquidation) {
            $date = $liquidation->created_at ?: Carbon::create($liquidation->period_year, $liquidation->period_month, 1);
            $timestamp = $date ? $date->timestamp : 0;

            $legacyMovements->push([
                'sort_key' => sprintf('%015d-%010d', $timestamp, $liquidation->id ?? 0),
                'date' => $date ? $date->format('Y-m-d') : null,
                'type_label' => 'Liquidacion',
                'description' => sprintf('Liquidacion %02d/%s', $liquidation->period_month, $liquidation->period_year),
                'amount' => (float) $liquidation->net_amount,
                'source_label' => 'Liquidacion',
            ]);
        }

        foreach ($periodRedemptions as $redemption) {
            $date = $redemption->requested_at ?: $redemption->created_at;
            $timestamp = $date ? $date->timestamp : 0;

            $legacyMovements->push([
                'sort_key' => sprintf('%015d-%010d', $timestamp, $redemption->id ?? 0),
                'date' => $date ? $date->format('Y-m-d') : null,
                'type_label' => 'Redencion',
                'description' => 'Redencion ' . ($redemption->redemptionProduct?->name ?? 'Producto'),
                'amount' => -1 * (float) $redemption->total_value,
                'source_label' => 'Redencion',
            ]);
        }

        if ($legacyMovements->isEmpty()) {
            $this->movements = [];
            $this->buildSummary($store, $storeLabel, $initialBalance, 0.0, 0.0, 0.0, $initialBalance);
            return;
        }

        $runningBalance = $initialBalance;
        $credits = 0.0;
        $debits = 0.0;
        $adjustments = 0.0;

        $this->movements = $legacyMovements
            ->sortBy('sort_key')
            ->map(function (array $movement) use (&$runningBalance, &$credits, &$debits, $storeLabel) {
                $amount = (float) ($movement['amount'] ?? 0);

                if ($amount >= 0) {
                    $credits += $amount;
                } else {
                    $debits += abs($amount);
                }

                $runningBalance += $amount;

                return [
                    'date' => $movement['date'],
                    'type_label' => $movement['type_label'],
                    'description' => $movement['description'],
                    'amount' => $amount,
                    'source_label' => $movement['source_label'],
                    'balance' => $runningBalance,
                    'store_label' => $storeLabel,
                ];
            })
            ->values()
            ->toArray();

        $this->buildSummary($store, $storeLabel, $initialBalance, $credits, $debits, $adjustments, $runningBalance);
    }
}
