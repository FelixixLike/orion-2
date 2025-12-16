<?php


/**
 * @author Andrés Felipe Martínez González <felixix-like@outlook.es>
 * @copyright 2025 Derechos Reservados. Uso exclusivo bajo licencia privada.
 */

namespace App\Domain\Admin\Filament\Pages;

use App\Domain\Store\Services\LiquidationCalculationService;
use App\Domain\Import\Models\Import;
use App\Domain\Import\Models\OperatorReport;
use App\Domain\Import\Models\Recharge;
use App\Domain\Store\Models\Liquidation;
use App\Domain\Store\Models\LiquidationItem;
use App\Domain\Store\Models\Store;
use App\Domain\Store\Jobs\CalculateLiquidationPreviewJob;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CrossingsPage extends Page implements HasForms
{
    use InteractsWithForms;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationLabel = 'Cruces';

    protected static ?string $title = 'Cruce de pagos Claro vs Movilco vs tenderos';

    protected string $view = 'filament.admin.crosses.pages.crossings-page-v2';

    public ?string $period = null;
    public ?int $storeId = null;
    public array $periods = [];
    public array $storeOptions = [];
    public array $summaries = [];
    public array $previewStores = [];
    public ?string $previewPeriod = null;
    public ?int $previewStoreId = null;
    public array $previewStoreLines = [];
    public array $detailedLines = [];
    public ?int $executionUserId = null;
    public array $selectedStoreIds = [];

    // Filtros para el MODAL de previsualización
    public ?string $previewSearch = '';
    public ?string $previewSort = 'name_asc';
    public ?string $previewFilterMode = 'all';
    public ?int $expandedStoreId = null;
    public bool $previewLimitExceeded = false;
    public int $previewPage = 1;
    public int $previewTotalPages = 1;
    public float $previewTotalAmount = 0.0;
    public float $grandTotalAmount = 0.0; // New: Total global del periodo

    // Progress Tracking
    public bool $calculating = false;
    public int $calculationProgress = 0;

    /**
     * Cache simple para status/resumen de tiendas por periodo.
     *
     * @var array<string,array{total:int,liquidated:int,pending:int}>
     */
    protected array $storeStatusCache = [];

    /**
     * Cache de tiendas por periodo sin líneas para evitar recalcular.
     *
     * @var array<string,array<int,array<string,mixed>>>
     */
    protected array $storeListCache = [];

    public static function getSlug(?Panel $panel = null): string
    {
        return 'crossings';
    }

    public static function getNavigationSort(): ?int
    {
        return 0;
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::guard('admin')->user();
        // Solo super_admin/administrator pueden ejecutar cruces; gerencia queda en solo lectura de listados, no aquí.
        return $user?->hasAnyRole(['super_admin', 'administrator'], 'admin') ?? false;
    }

    public static function canAccess(array $parameters = []): bool
    {
        return static::shouldRegisterNavigation();
    }

    public function mount(): void
    {
        // Solo cargar la lista de periodos - ultra rápido
        $this->periods = $this->getAvailablePeriods();
        $this->period = $this->periods[0] ?? null;
        $this->storeOptions = [];
        $this->summaries = [];
    }



    public function loadSummaries(): void
    {
        // Este método se llama después del render inicial
        if ($this->period) {
            $this->summaries = $this->buildSummaries();
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('period')
                ->label('Periodo (YYYY-MM)')
                ->options(collect($this->periods)->mapWithKeys(fn($p) => [$p => $p]))
                ->searchable()
                ->placeholder('Ver último periodo')
                ->live()
                ->afterStateUpdated(fn() => $this->summaries = $this->buildSummaries()),
        ])->columns(1);
    }

    public function updatedPeriod(): void
    {
        $this->summaries = $this->buildSummaries();
        $this->resetPreview();
    }

    public function updatedStoreId(): void
    {
        $this->summaries = $this->buildSummaries();
        $this->resetPreview();
    }

    public function loadPreview(string $period): void
    {
        $userId = Auth::guard('admin')->id();
        $this->executionUserId = (int) $userId;

        if (!$userId) {
            Notification::make()
                ->title('Error de sesión')
                ->body('No se pudo identificar al usuario admin. Por favor recarga la página.')
                ->danger()
                ->send();
            return;
        }

        $userId = (int) $userId;

        $this->previewPeriod = $period;
        $this->previewStoreId = null;
        $this->previewStoreLines = [];
        $this->previewPage = 1;

        $key = 'preview_data_' . $userId . '_' . $period;
        $progressKey = 'preview_progress_' . $userId . '_' . $period;

        // Check if data already exists OR if already completed
        if (Cache::has($key)) {
            $this->updateVisiblePreview();
            $this->selectedStoreIds = $this->collectPendingStoreIds($this->previewStores);
            $this->dispatch('open-modal', id: 'liquidation-preview-modal');
            return;
        }

        // If not cached, check if period is already fully liquidated to avoid re-running heavy job
        // If status is 'Completadas' or 'Liquidada', we can just build from DB directly without job
        $summary = collect($this->summaries)->firstWhere('period', $period);
        if ($summary && in_array($summary['status'], ['Completadas', 'Liquidada'])) {
            $this->calculating = false;
            // Force cache population synchronously from DB for speed since it's just reading
            $data = $this->calculatePreviewStores($period, true);
            Cache::put($key, $data, 600);

            $this->updateVisiblePreview();
            $this->selectedStoreIds = $this->collectPendingStoreIds($this->previewStores);
            $this->dispatch('open-modal', id: 'liquidation-preview-modal');
            return;
        }

        // If not, START JOB
        $this->calculating = true;
        $this->calculationProgress = 0;
        Cache::put($progressKey, 0, 600);

        CalculateLiquidationPreviewJob::dispatch(
            $period,
            $userId,
            $progressKey,
            $key
        );
    }

    public function checkPreviewProgress(): void
    {
        if (!$this->previewPeriod || !$this->calculating) {
            return;
        }

        $userId = (int) Auth::guard('admin')->id();
        $progressKey = 'preview_progress_' . $userId . '_' . $this->previewPeriod;
        $progress = Cache::get($progressKey, 0);

        $this->calculationProgress = (int) $progress;

        if ($progress >= 100) {
            // Check if result data actually exists before opening modal
            $key = 'preview_data_' . $userId . '_' . $this->previewPeriod;
            if (!Cache::has($key)) {
                // If 100% but no data, something went wrong (job killed, finalizer failed). Reset.
                $this->calculating = false;
                Cache::forget($progressKey);
                Notification::make()
                    ->title('Error en cálculo')
                    ->body('El proceso finalizó pero no se generaron datos. Por favor intenta de nuevo.')
                    ->warning()
                    ->send();
                return;
            }

            $this->calculating = false;
            $this->updateVisiblePreview();
            $this->selectedStoreIds = $this->collectPendingStoreIds($this->previewStores);
            $this->dispatch('open-modal', id: 'liquidation-preview-modal');
        }
    }

    public function updatedPreviewSearch(): void
    {
        $this->previewPage = 1; // Reset a página 1 al buscar
        $this->updateVisiblePreview();
    }

    public function updatedPreviewSort(): void
    {
        $this->previewPage = 1; // Reset a página 1 al ordenar
        $this->updateVisiblePreview();
    }

    public function nextPage(): void
    {
        $this->previewPage++;
        $this->updateVisiblePreview();
    }

    public function previousPage(): void
    {
        if ($this->previewPage > 1) {
            $this->previewPage--;
            $this->updateVisiblePreview();
        }
    }

    private function updateVisiblePreview(): void
    {
        if (!$this->previewPeriod)
            return;

        $stores = $this->getCacheData();

        // 1. Filtrar
        if ($this->previewSearch) {
            $search = strtolower($this->previewSearch);
            $stores = $stores->filter(
                fn($s) =>
                str_contains(strtolower($s['name']), $search) ||
                str_contains((string) $s['idpos'], $search)
            );
        }

        // 2. Calcular Total Filtrado (Antes de paginar)
        $this->previewTotalAmount = (float) $stores->sum('total');

        // Calcular Gran Total del PERIODO COMPLETO (sin filtros)
        // CRITICAL FIX: Sum ALL lines, not just stores.
        // The previous calculation summed 'stores', which excluded orphans.
        // To match the CSV (which lists lines), we must sum lines.
        // The cache structure is ['stores' => [...], 'lines' => [...]]?
        // Wait, getCacheData() currently returns 'stores' collection.
        // We need the raw full data to access 'lines'.
        // Let's check how cache is stored. Cache stores the 'stores' array mostly.

        // REVISIT: The service returns ['stores' => ..., 'lines' => ...].
        // CrossingsPage::getCacheData currently returns collect($data['stores'] ?? []).
        // This is why we miss the orphans!

        // HOWEVER, to fix this quickly without refactoring the whole cache structure reader:
        // We need to fetch the 'lines' key from the raw cache if available.

        $userId = (int) Auth::guard('admin')->id();
        $key = 'preview_data_' . $userId . '_' . $this->previewPeriod;
        $rawData = Cache::get($key); // This should be the full array ['stores' => ..., 'lines' => ...]

        if (is_array($rawData) && isset($rawData['lines'])) {
            $this->grandTotalAmount = collect($rawData['lines'])->sum('pago_residual');
        } else {
            // Fallback if cache structure is different (e.g. just stores list)
            $allStoresUnfiltered = $this->getCacheData();
            $this->grandTotalAmount = $allStoresUnfiltered->sum('total');
        }

        // 2. Calcular Total Filtrado (Antes de paginar)
        // ESTE es el valor que debe coincidir con el archivo descargado.
        // Si hay busqueda, es el total de la busqueda. Si no, es el total del periodo.
        $this->grandTotalAmount = (float) $stores->sum('total');

        // 3. Ordenar
        if ($this->previewSort) {
            $stores = match ($this->previewSort) {
                'name_asc' => $stores->sortBy('name'),
                'name_desc' => $stores->sortByDesc('name'),
                'total_desc' => $stores->sortByDesc('total'),
                'total_asc' => $stores->sortBy('total'),
                'idpos_asc' => $stores->sortBy('idpos'),
                'idpos_desc' => $stores->sortByDesc('idpos'),
                default => $stores,
            };
        }

        // 4. Paginación
        $perPage = 50;

        // 3. Paginación
        // Asegurar que la página actual es válida
        if ($this->previewPage < 1)
            $this->previewPage = 1;
        if ($this->previewPage > $this->previewTotalPages && $this->previewTotalPages > 0)
            $this->previewPage = $this->previewTotalPages;

        $this->previewStores = $stores->forPage($this->previewPage, $perPage)->map(function ($s) {
            unset($s['lines']);
            return $s;
        })->values()->toArray();

        // Verificar si quedan pendientes en TOTAL (no solo en la página actual)
        $this->hasPendingLiquidations = $stores->contains(fn($s) => ($s['status'] ?? 'pending') !== 'liquidated');
    }

    public bool $hasPendingLiquidations = false;

    public function selectPreviewStore(int $storeId): void
    {
        $allStores = $this->getCacheData();
        $store = $allStores->firstWhere('store_id', $storeId);

        if (!$store) {
            return;
        }

        $this->previewStoreId = $storeId;
        $this->previewStoreLines = $store['lines'] ?? [];
    }

    public function closePreview(): void
    {
        $this->resetPreview();
        $this->dispatch('close-modal', id: 'liquidation-preview-modal');
    }

    /**
     * @return array<int,string>
     */
    private function getAvailablePeriods(): array
    {
        // Cache por 10 minutos - los periodos no cambian frecuentemente
        return Cache::remember('crossing_available_periods_v2', 600, function () {
            // Solo últimos 12 periodos para evitar consultas masivas
            $importPeriods = Import::query()
                ->where('type', 'operator_report')
                ->where('status', 'completed')
                ->whereNotNull('period')
                ->distinct()
                ->orderByDesc('period')
                ->limit(12)
                ->pluck('period')
                ->toArray();

            // Períodos con liquidaciones (últimos 12)
            $liquidationPeriods = Liquidation::query()
                ->selectRaw("DISTINCT period_year, period_month, CONCAT(period_year, '-', LPAD(period_month::text,2,'0')) as period")
                ->orderByDesc('period_year')
                ->orderByDesc('period_month')
                ->limit(12)
                ->pluck('period')
                ->toArray();

            return collect($importPeriods)
                ->merge($liquidationPeriods)
                ->unique()
                ->sortDesc()
                ->take(12) // Solo mostrar últimos 12 meses
                ->values()
                ->toArray();
        });
    }

    private function buildSummaries(): array
    {
        $periods = $this->period ? [$this->period] : $this->periods;

        return collect($periods)
            ->filter() // Elimina nulls
            ->map(fn($period) => $this->buildSummaryForPeriod((string) $period))
            ->filter()
            ->values()
            ->toArray();
    }

    private function buildSummaryForPeriod(string $period): ?array
    {
        $storeId = $this->storeId;
        $cacheKey = "crossing_summary_v3_{$period}_" . ($storeId ?? 'all');

        // Cache por 5 minutos para evitar recálculos masivos en cada click
        return Cache::remember($cacheKey, 300, function () use ($period, $storeId) {
            $latestImportId = null;
            $latestRechargeImportId = null;

            if ($storeId) {
                $items = LiquidationItem::query()
                    ->where('period', $period)
                    ->whereHas('liquidation', fn($q) => $q->where('store_id', $storeId))
                    ->get(['total_commission', 'movilco_recharge_amount', 'base_liquidation_final', 'final_amount']);

                $totalCommissionClaro = (float) $items->sum('total_commission');
                $totalRecargas = (float) $items->sum('movilco_recharge_amount');
                $baseLiquidada = (float) $items->sum('base_liquidation_final');
                $totalPagado = (float) $items->sum('final_amount');
                $totalLines = $items->count();
            } else {
                // MODIFICACIÓN: Contar TODAS las filas crudas (is_consolidated=false) del periodo.
                [$year, $month] = $this->parsePeriod($period);

                $latestImportId = Import::where('type', 'operator_report')
                    ->where('status', 'completed')
                    ->where('period', $period)
                    ->latest()
                    ->value('id');

                // Consulta base para todo el periodo - OPTIMIZADA
                // Usamos toBase() para evitar hidratación de modelos innecesaria aunque sea un count, por seguridad.
                $stats = DB::table('operator_reports')
                    ->where('period_year', $year)
                    ->where('period_month', $month)
                    ->where('is_consolidated', false)
                    ->selectRaw('COUNT(*) as count, SUM(total_commission) as total')
                    ->first();

                $totalLines = (int) ($stats->count ?? 0);
                $totalCommissionClaro = (float) ($stats->total ?? 0.0);


                // Recargas
                $rechargeImportIds = Import::where('type', 'recharge')
                    ->where('status', 'completed')
                    ->where('period', $period)
                    ->pluck('id');

                $latestRechargeImportId = $rechargeImportIds->last(); // Keep for reference if needed, assuming pluck order or just take one. Use explicit latest() query if we need the strict absolute latest for metadata.

                // Refetch latest properly for metadata if needed
                if ($rechargeImportIds->isNotEmpty()) {
                    $latestRechargeImportId = Import::where('type', 'recharge')
                        ->where('status', 'completed')
                        ->where('period', $period)
                        ->latest()
                        ->value('id');

                    $totalRecargas = (float) Recharge::query()
                        ->whereIn('import_id', $rechargeImportIds)
                        ->sum('recharge_amount');
                } else {
                    $latestRechargeImportId = null;
                    $totalRecargas = 0.0;
                }

                $baseLiquidada = (float) LiquidationItem::query()
                    ->where('period', $period)
                    ->sum('base_liquidation_final');

                $totalPagado = (float) LiquidationItem::query()
                    ->where('period', $period)
                    ->sum('final_amount');
            }

            $status = $this->getLiquidationStatusLabel($period, $storeId);

            $difference = $totalCommissionClaro - $totalPagado;
            $alert = $difference < 0;
            $differenceRate = $totalCommissionClaro > 0 ? ($difference / $totalCommissionClaro) : 0;

            return [
                'period' => $period,
                'store_id' => $storeId,
                'total_lines' => $totalLines,
                'total_commission_claro' => $totalCommissionClaro,
                'total_recargas' => $totalRecargas,
                'base_liquidada' => $baseLiquidada,
                'total_pagado' => $totalPagado,
                'difference' => $difference,
                'difference_rate' => $differenceRate,
                'alert' => $alert,
                'status' => $status,
                'operator_import_id' => $latestImportId,
                'recharge_import_id' => $latestRechargeImportId,
            ];
        });
    }

    private function resetPreview(): void
    {
        $this->previewPeriod = null;
        $this->previewStores = [];
        $this->previewStoreId = null;
        $this->previewStoreLines = [];
        $this->detailedLines = [];
        $this->expandedStoreId = null;
        $this->selectedStoreIds = [];
    }

    private function getCacheData(): Collection
    {
        $userId = $this->executionUserId ?? (int) Auth::guard('admin')->id();
        if (!$this->previewPeriod || !$userId)
            return collect([]);

        $key = 'preview_data_' . $userId . '_' . $this->previewPeriod;
        $data = Cache::get($key);

        if ($data === null) {
            $data = $this->calculatePreviewStores($this->previewPeriod, true);
            Cache::put($key, $data, 600);
        }

        // If data follows new structure ['stores' => ..., 'lines' => ...], return stores
        if (is_array($data) && isset($data['stores'])) {
            return collect($data['stores']);
        }

        return collect($data);
    }

    private function calculatePreviewStores(string $period, bool $withLines = true): array
    {
        if (!$withLines && isset($this->storeListCache[$period])) {
            return $this->storeListCache[$period];
        }

        [$year, $month] = $this->parsePeriod($period);
        $finalStoresMap = [];

        // 1. OBTENER LIQUIDACIONES CERRADAS (Fuente de la Verdad Inmutable)
        // Esto garantiza que si ya se liquidó, mostramos LO QUE SE GUARDÓ, no un recalculo.
        $closedQuery = Liquidation::query()
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->where('status', 'closed');

        // Solo cargar items si realmente se necesitan las líneas
        if ($withLines) {
            $closedQuery->with(['items', 'store']);
        } else {
            $closedQuery->with('store');
        }

        $closedLiquidations = $closedQuery->get();

        foreach ($closedLiquidations as $liq) {
            $storeId = $liq->store_id;
            $items = ($withLines && $liq->relationLoaded('items')) ? $liq->items : collect();

            $totalPagado = (float) $items->sum('final_amount');
            // Si no hay items cargados (caso raro o sin lineas), usar el total de la cabecera
            if ($items->isEmpty() && $liq->gross_amount > 0) {
                $totalPagado = (float) $liq->gross_amount;
            }

            $finalStoresMap[$storeId] = [
                'store_id' => $storeId,
                'name' => $liq->store?->name ?? 'Tienda Liquidada',
                'idpos' => $liq->store?->idpos ?? 'N/D',
                'total' => $totalPagado,
                'status' => 'liquidated', // IMPORTANTE: Marcamos como liquidada para que UI lo sepa
                'lines' => $withLines ? $items->map(fn($item) => [
                    'iccid' => $item->iccid,
                    'phone_number' => $item->phone_number,
                    'residual_percentage' => $item->residual_percentage,
                    'movilco_recharge_amount' => $item->movilco_recharge_amount,
                    'base_liquidation_final' => $item->base_liquidation_final,
                    'pago_residual' => $item->final_amount,
                    'status' => 'liquidated'
                ])->toArray() : [],
            ];
        }

        // 2. OBTENER PENDIENTES (Cálculo en vivo de lo que falta)
        $calculationService = app(LiquidationCalculationService::class);
        $pendingResult = $calculationService->calculateForPeriod($period); // Esto trae solo reportes SIN liquidation_item_id
        $pendingStoresRaw = $pendingResult['stores'] ?? [];

        // Identificar qué tiendas pendientes NO están ya en el mapa de liquidadas
        // (Aunque teóricamente calculationService filtra, por seguridad hacemos merge)
        $pendingStoreIds = array_diff(array_keys($pendingStoresRaw), array_keys($finalStoresMap));

        if (!empty($pendingStoreIds)) {
            $meta = Store::query()
                ->select(['id', 'name', 'idpos'])
                ->whereIn('id', $pendingStoreIds)
                ->get()
                ->keyBy('id');

            foreach ($pendingStoreIds as $storeId) {
                $raw = $pendingStoresRaw[$storeId];
                $store = $meta->get($storeId);

                $finalStoresMap[$storeId] = [
                    'store_id' => (int) $storeId,
                    'name' => $store?->name ?? 'Tienda',
                    'idpos' => $store?->idpos ?? 'N/D',
                    'total' => (float) ($raw['total'] ?? 0),
                    'status' => 'pending',
                    'lines' => $withLines ? ($raw['lines'] ?? []) : [],
                ];
            }
        }

        // 3. RESULTADO FINAL UNIFICADO
        $rows = collect($finalStoresMap)
            ->filter(fn($row) => $row['total'] > 0) // Opcional: ocultar tiendas en 0
            ->sortBy(fn($row) => $row['idpos'])
            ->values()
            ->toArray();

        if (!$withLines) {
            $this->storeListCache[$period] = $rows;
        }

        return $rows;
    }

    private function getLiquidationStatusLabel(string $period, ?int $storeId): string
    {
        if ($storeId) {
            [$year, $month] = $this->parsePeriod($period);

            $hasClosed = Liquidation::query()
                ->where('period_year', $year)
                ->where('period_month', $month)
                ->where('store_id', $storeId)
                ->where('status', 'closed')
                ->exists();

            return $hasClosed ? 'Liquidada' : 'Pendiente';
        }

        $summary = $this->getStoreStatusSummary($period);

        if ($summary['total'] === 0) {
            return 'Sin datos';
        }

        if ($summary['pending'] === 0) {
            return 'Completadas';
        }

        if ($summary['liquidated'] === 0) {
            return 'Pendiente';
        }

        return 'Parcial';
    }

    private function parsePeriod(string $period): array
    {
        [$year, $month] = explode('-', $period);
        return [(int) $year, (int) $month];
    }

    public function selectAllPending(): void
    {
        if (!$this->previewPeriod) {
            $this->selectedStoreIds = [];
            return;
        }

        $userId = (int) Auth::guard('admin')->id();
        $key = 'preview_data_' . $userId . '_' . $this->previewPeriod;
        $allStores = Cache::get($key, []);

        if (empty($allStores)) {
            $this->selectedStoreIds = [];
            return;
        }

        $this->selectedStoreIds = $this->collectPendingStoreIds($allStores);
    }

    public function clearSelection(): void
    {
        $this->selectedStoreIds = [];
    }

    public function liquidateSelected(): void
    {
        if (!$this->previewPeriod) {
            return;
        }

        $storeIds = collect($this->selectedStoreIds)
            ->map(fn($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($storeIds)) {
            Notification::make()
                ->title('Selecciona al menos una tienda')
                ->warning()
                ->send();
            return;
        }

        // Crear registro de proceso en background
        $process = \App\Domain\Admin\Models\BackgroundProcess::create([
            'user_id' => Auth::guard('admin')->id(),
            'type' => 'liquidation',
            'name' => "Liquidando {$this->previewPeriod} (" . count($storeIds) . " tiendas)",
            'total' => count($storeIds),
            'progress' => 0,
            'status' => 'pending',
        ]);

        // Despachar Job
        \App\Domain\Store\Jobs\LiquidationJob::dispatch(
            $storeIds,
            $this->previewPeriod,
            Auth::guard('admin')->id(),
            $process->id
        );

        // Limpiar cache para que al recargar se vea actualizado
        $key = 'preview_data_' . Auth::guard('admin')->id() . '_' . $this->previewPeriod;
        Cache::forget($key);

        // Limpiar cache de resumen (CRÍTICO para actualizar "Total Pagado")
        Cache::forget("crossing_summary_v3_{$this->previewPeriod}_all");
        foreach ($storeIds as $sid) {
            Cache::forget("crossing_summary_v3_{$this->previewPeriod}_{$sid}");
        }

        unset($this->storeStatusCache[$this->previewPeriod], $this->storeListCache[$this->previewPeriod]);

        // Cerrar modal y notificar
        $this->closePreview();

        Notification::make()
            ->title('Liquidación iniciada')
            ->body('El proceso se está ejecutando en segundo plano. Puedes seguir trabajando y ver el progreso en la barra inferior.')
            ->success()
            ->send();
    }

    public function liquidateAll(): void
    {
        $this->selectAllPending();
        $this->liquidateSelected();
        $this->closePreview();
    }

    private function collectPendingStoreIds(array $stores): array
    {
        return collect($stores)
            ->filter(fn($store) => ($store['status'] ?? 'pending') !== 'liquidated')
            ->pluck('store_id')
            ->map(fn($id) => (int) $id)
            ->values()
            ->toArray();
    }

    private function getStoreStatusesForPeriod(string $period, array $storeIds): array
    {
        if (empty($storeIds)) {
            return [];
        }

        [$year, $month] = $this->parsePeriod($period);

        return Liquidation::query()
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->whereIn('store_id', $storeIds)
            ->where('status', 'closed')
            ->pluck('status', 'store_id')
            ->map(fn() => 'liquidated')
            ->toArray();
    }

    public function liquidateSelectedAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('liquidateSelected')
            ->label('Liquidar seleccionadas')
            ->color('success')
            ->icon('heroicon-o-banknotes')
            ->requiresConfirmation()
            ->modalHeading('¿Liquidar tiendas seleccionadas?')
            ->modalDescription('Se generarán los movimientos de saldo por lo que esta acción es crítica. Para confirmar, escribe "ESTOY DE ACUERDO" en el campo de abajo.')
            ->form([
                \Filament\Forms\Components\TextInput::make('confirmation')
                    ->label('Escribe "ESTOY DE ACUERDO" para confirmar')
                    ->required()
                    ->regex('/^ESTOY DE ACUERDO$/')
                    ->validationAttribute('confirmación')
                    ->validationMessages([
                        'regex' => 'Debes escribir exactamente "ESTOY DE ACUERDO".',
                    ]),
            ])
            ->modalSubmitActionLabel('Confirmar y Liquidar')
            ->action(function () {
                $this->liquidateSelected();
            });
    }

    public function toggleSort(string $field): void
    {
        // Si ya está ordenado por este campo, invertimos el orden
        if ($this->previewSort === $field . '_asc') {
            $this->previewSort = $field . '_desc';
        } else {
            // Default a ascendente, excepto para 'total' (valor) que suele ser mejor descendente primero
            if ($field === 'total' && $this->previewSort !== 'total_asc') {
                $this->previewSort = 'total_desc';
            } else {
                $this->previewSort = $field . '_asc';
            }
        }
    }

    public function getFilteredPreviewStores(): array
    {
        // Este método ya no filtra en vivo, devuelve lo que hay en previewStores que ya está filtrado y paginado
        return $this->previewStores;
    }

    public function exportPreview()
    {
        // Para exportar, leemos del caché completo
        if (!$this->previewPeriod)
            return;

        $allStores = $this->getCacheData();

        // Aplicar filtros de búsqueda actuales
        if ($this->previewSearch) {
            $search = strtolower($this->previewSearch);
            $allStores = $allStores->filter(
                fn($s) =>
                str_contains(strtolower($s['name']), $search) ||
                str_contains((string) $s['idpos'], $search)
            );
        }

        // Ordenar
        if ($this->previewSort) {
            $allStores = match ($this->previewSort) {
                'name_asc' => $allStores->sortBy('name'),
                'name_desc' => $allStores->sortByDesc('name'),
                'total_desc' => $allStores->sortByDesc('total'),
                'total_asc' => $allStores->sortBy('total'),
                'idpos_asc' => $allStores->sortBy('idpos'),
                'idpos_desc' => $allStores->sortByDesc('idpos'),
                default => $allStores,
            };
        }

        $fileName = 'liquidacion-resumen-' . ($this->previewPeriod ?? now()->format('Y-m')) . '.csv';

        return response()->streamDownload(function () use ($allStores) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF"); // BOM para Excel

            // Cabeceras
            fputcsv($handle, ['CÓDIGO', 'NOMBRE', 'ESTADO', 'VALOR']);

            foreach ($allStores as $s) {
                fputcsv($handle, [
                    $s['idpos'],
                    $s['name'],
                    ($s['status'] ?? '') === 'liquidated' ? 'Liquidada' : 'Pendiente',
                    $s['total'],
                ]);
            }
            fclose($handle);
        }, $fileName);
    }

    public function exportPreviewDetails()
    {
        if (!$this->previewPeriod)
            return;

        // 1. Obtener Datos "Pre-Calculados" de la Memoria (Rápido, sin CPU intensivo)
        $allStores = $this->getCacheData();

        // 2. Filtrar (Rápido)
        if ($this->previewSearch) {
            $search = strtolower($this->previewSearch);
            $allStores = $allStores->filter(
                fn($s) =>
                str_contains(strtolower($s['name']), $search) ||
                str_contains((string) $s['idpos'], $search)
            );
        }

        // 3. Ordenar (Rápido)
        if ($this->previewSort) {
            $allStores = match ($this->previewSort) {
                'name_asc' => $allStores->sortBy('name'),
                'name_desc' => $allStores->sortByDesc('name'),
                'total_desc' => $allStores->sortByDesc('total'),
                'total_asc' => $allStores->sortBy('total'),
                'idpos_asc' => $allStores->sortBy('idpos'),
                'idpos_desc' => $allStores->sortByDesc('idpos'),
                default => $allStores,
            };
        }

        $fileName = 'liquidacion-detalle-' . $this->previewPeriod . '-' . time() . '.csv';

        // 4. Stream Directo (IO Bound - No consume CPU de App, solo trasfiere datos)
        return response()->streamDownload(function () use ($allStores) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF"); // BOM

            fputcsv($handle, ['CÓDIGO TIENDA', 'NOMBRE TIENDA', 'ICCID', 'TELÉFONO', '% COMISIÓN', 'RECARGA MOVILCO', 'BASE LIQUIDACIÓN', 'TOTAL A PAGAR']);

            foreach ($allStores as $store) {
                if (empty($store['lines']))
                    continue;

                foreach ($store['lines'] as $line) {
                    fputcsv($handle, [
                        $store['idpos'],
                        $store['name'],
                        $line['iccid'] ?? 'N/D',
                        $line['phone_number'] ?? 'N/D',
                        number_format($line['residual_percentage'], 2) . '%',
                        $line['movilco_recharge_amount'],
                        $line['base_liquidation_final'],
                        $line['pago_residual'],
                    ]);
                }
            }

            fclose($handle);
        }, $fileName);
    }

    private function getStoreStatusSummary(string $period): array
    {
        if (isset($this->storeStatusCache[$period])) {
            return $this->storeStatusCache[$period];
        }

        [$year, $month] = $this->parsePeriod($period);

        // 1. Tiendas Liquidadas (Count distinct store_id from Liquidations)
        $liquidatedCount = Liquidation::query()
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->where('status', 'closed')
            ->distinct('store_id')
            ->count('store_id');

        // 2. Tiendas Pendientes (Aprox: Reports without liquidation_item_id)
        // Usamos una consulta optimizada para obtener IDPOS únicos con pendientes
        // Nota: Esto asume la última condición, pero para un resumen rápido es suficiente.
        $pendingCount = DB::table('operator_reports')
            ->join('simcards', 'operator_reports.simcard_id', '=', 'simcards.id')
            ->join('sales_conditions', 'sales_conditions.simcard_id', '=', 'simcards.id')
            ->where('operator_reports.period_year', $year)
            ->where('operator_reports.period_month', $month)
            ->whereNull('operator_reports.liquidation_item_id')
            ->where('operator_reports.is_consolidated', true)
            ->distinct('sales_conditions.idpos')
            ->count('sales_conditions.idpos');

        // Ajuste: si una tienda tiene pendientes Y liquidadas (reprocesos), se contará en ambos lados
        // Para simplificar el "Total", sumamos. Ojo que pendingCount podría incluir tiendas que ya tienen OTROS items liquidados.
        // Lo ideal sería "Tiendas con AL MENOS un pendiente".

        $total = $liquidatedCount + $pendingCount;

        $summary = [
            'total' => $total,
            'liquidated' => $liquidatedCount,
            'pending' => $pendingCount,
        ];

        $this->storeStatusCache[$period] = $summary;

        return $summary;
    }
    public function deleteImport(int $importId)
    {
        $import = Import::find($importId);
        if (!$import)
            return;

        // Intentar deducir periodo del import para validar liquidaciones
        $year = null;
        $month = null;

        if ($import->period) {
            try {
                $date = \Illuminate\Support\Carbon::createFromFormat('Y-m', $import->period);
                $year = (int) $date->format('Y');
                $month = (int) $date->format('m');
            } catch (\Exception $e) {
            }
        }

        // Si no tiene periodo en el import, intentar deducirlo de los hijos
        if (!$year && $import->type === 'operator_report') {
            $first = $import->operatorReports()->first();
            if ($first) {
                $year = $first->period_year;
                $month = $first->period_month;
            }
        } elseif (!$year && $import->type === 'recharge') {
            $first = $import->recharges()->first();
            if ($first) {
                $year = $first->period_year;
                $month = $first->period_month;
            }
        }

        // VALIDACIÓN: Si hay liquidaciones para este periodo, NO BORRAR.
        if ($year && $month) {
            $exists = Liquidation::where('period_year', $year)
                ->where('period_month', $month)
                ->exists();

            if ($exists) {
                Notification::make()
                    ->title('Acción Bloqueada')
                    ->body("El periodo $year-$month ya tiene liquidaciones generadas. No se puede eliminar el cargue.")
                    ->danger()
                    ->send();
                return;
            }
        }

        // PROCEDER A BORRAR
        try {
            if ($import->type === 'operator_report') {
                OperatorReport::where('import_id', $import->id)->delete();
            } elseif ($import->type === 'recharge') {
                Recharge::where('import_id', $import->id)->delete();
            }

            $import->delete();

            Notification::make()->title('Cargue eliminado correctamente')->success()->send();

            // Refrescar la vista
            $this->summaries = $this->buildSummaries();

        } catch (\Exception $e) {
            Notification::make()->title('Error al eliminar')->body($e->getMessage())->danger()->send();
        }
    }

    public ?string $periodToDelete = null;
    public ?string $importTypeToDelete = null;
    public string $importTypeLabelToDelete = '';

    public function promptDeleteImportsForPeriod(string $period, string $type, string $label)
    {
        $this->periodToDelete = $period;
        $this->importTypeToDelete = $type;
        $this->importTypeLabelToDelete = $label;
        $this->dispatch('open-modal', id: 'delete-import-modal');
    }

    public function confirmDeleteImport()
    {
        if ($this->periodToDelete && $this->importTypeToDelete) {
            $this->deletePeriodImports($this->periodToDelete, $this->importTypeToDelete);
        }
        $this->dispatch('close-modal', id: 'delete-import-modal');
        $this->periodToDelete = null;
        $this->importTypeToDelete = null;
    }

    private function deletePeriodImports(string $period, string $type)
    {
        // 1. VALIDACIÓN: Si hay liquidaciones para este periodo, NO BORRAR.
        [$year, $month] = $this->parsePeriod($period);
        $exists = Liquidation::where('period_year', $year)
            ->where('period_month', $month)
            ->exists();

        if ($exists) {
            Notification::make()
                ->title('Acción Bloqueada')
                ->body("El periodo $period ya tiene liquidaciones generadas. No se pueden eliminar los cargues.")
                ->danger()
                ->send();
            return;
        }

        // 2. BUSCAR TODOS LOS IMPORTS DEL PERIODO Y TIPO
        $imports = Import::query()
            ->where('type', $type)
            // Buscar por coincidencia exacta de periodo O por coincidencia en registros hijos
            ->where(function ($q) use ($period, $type, $year, $month) {
                $q->where('period', $period);

                if ($type === 'operator_report') {
                    $q->orWhereHas(
                        'operatorReports',
                        fn($sq) =>
                        $sq->where('period_year', $year)->where('period_month', $month)
                    );
                } elseif ($type === 'recharge') {
                    $q->orWhereHas(
                        'recharges',
                        fn($sq) =>
                        $sq->where('period_year', $year)->where('period_month', $month)
                    );
                }
            })
            ->get();

        if ($imports->isEmpty()) {
            Notification::make()->title('No se encontraron importaciones para borrar')->warning()->send();
            return;
        }

        // 3. BORRAR
        try {
            $count = $imports->count();
            foreach ($imports as $import) {
                if ($type === 'operator_report') {
                    OperatorReport::where('import_id', $import->id)->delete();
                } elseif ($type === 'recharge') {
                    Recharge::where('import_id', $import->id)->delete();
                }
                $import->delete();
            }

            Notification::make()
                ->title("Se eliminaron $count cargue(s) correctamente")
                ->body('El periodo ha quedado limpio de estos datos.')
                ->success()
                ->send();

            // Refrescar la vista
            $this->summaries = $this->buildSummaries();

        } catch (\Exception $e) {
            Notification::make()->title('Error al eliminar')->body($e->getMessage())->danger()->send();
        }
    }

    public function toggleStoreDetails(int $storeId): void
    {
        if ($this->expandedStoreId === $storeId) {
            $this->expandedStoreId = null;
            $this->detailedLines = [];
        } else {
            $this->expandedStoreId = $storeId;
            $allStores = $this->getCacheData();
            $store = $allStores->firstWhere('store_id', $storeId);
            $this->detailedLines = $store && isset($store['lines']) ? $store['lines'] : [];
        }
    }
}
