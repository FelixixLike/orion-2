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
        $this->periods = $this->getAvailablePeriods();
        $this->period = $this->periods[0] ?? null;
        $this->storeOptions = Store::query()
            ->select(['id', 'idpos', 'name'])
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn(Store $store) => [$store->id => ($store->idpos ? "{$store->idpos} - " : '') . ($store->name ?? 'Tienda')])
            ->toArray();

        $this->summaries = $this->buildSummaries();
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
        $this->previewPeriod = $period;
        $this->previewStoreId = null;
        $this->previewStoreLines = [];
        $this->previewPage = 1;

        // Calcular TODO y guardar en Cache por 10 minutos
        $allStores = $this->calculatePreviewStores($period, true);
        $this->previewTotalAmount = collect($allStores)->sum('total');

        $key = 'preview_data_' . Auth::id() . '_' . $period;
        Cache::put($key, $allStores, 600);

        // Cargar vista parcial
        $this->updateVisiblePreview();

        $this->selectedStoreIds = $this->collectPendingStoreIds($this->previewStores); // Esto solo seleccionará de los visibles inicialmente, pero está bien para "Select All" si cambiamos lógica

        // Abrir modal
        $this->dispatch('open-modal', id: 'liquidation-preview-modal');
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

        $key = 'preview_data_' . Auth::id() . '_' . $this->previewPeriod;
        $allStores = Cache::get($key);

        // Fallback: Si no hay datos en caché (expiró o se borró), recalcular
        if ($allStores === null) {
            $allStores = $this->calculatePreviewStores($this->previewPeriod, true);
            Cache::put($key, $allStores, 600);
        }

        $stores = collect($allStores);

        // 1. Filtrar
        if ($this->previewSearch) {
            $search = strtolower($this->previewSearch);
            $stores = $stores->filter(
                fn($s) =>
                str_contains(strtolower($s['name']), $search) ||
                str_contains((string) $s['idpos'], $search)
            );
        }

        // 2. Ordenar
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

        $totalResults = $stores->count();
        $perPage = 50;
        $this->previewTotalPages = (int) ceil($totalResults / $perPage);
        $this->previewLimitExceeded = $totalResults > $perPage; // Mantenemos flag por si acaso, aunque ahora paginamos

        // 3. Paginación
        // Asegurar que la página actual es válida
        if ($this->previewPage < 1)
            $this->previewPage = 1;
        if ($this->previewPage > $this->previewTotalPages && $this->previewTotalPages > 0)
            $this->previewPage = $this->previewTotalPages;

        $this->previewStores = $stores->forPage($this->previewPage, $perPage)->values()->toArray();

        // Verificar si quedan pendientes en TOTAL (no solo en la página actual)
        $this->hasPendingLiquidations = $stores->contains(fn($s) => ($s['status'] ?? 'pending') !== 'liquidated');
    }

    public bool $hasPendingLiquidations = false;

    public function selectPreviewStore(int $storeId): void
    {
        $store = collect($this->previewStores)->firstWhere('store_id', $storeId);
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
        // Solo mostrar períodos que tengan OperatorReports consolidados con datos
        $importPeriods = Import::query()
            ->where('type', 'operator_report')
            ->where('status', 'completed')
            ->whereNotNull('period')
            ->whereHas('operatorReports', function ($query) {
                $query->where('is_consolidated', true);
            })
            ->distinct()
            ->orderByDesc('period')
            ->pluck('period')
            ->toArray();

        // Períodos con liquidaciones
        $liquidationPeriods = Liquidation::query()
            ->selectRaw("DISTINCT period_year, period_month, CONCAT(period_year, '-', LPAD(period_month::text,2,'0')) as period")
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->pluck('period')
            ->toArray();

        return collect($importPeriods)
            ->merge($liquidationPeriods)
            ->unique()
            ->sortDesc()
            ->values()
            ->toArray();
    }

    private function buildSummaries(): array
    {
        $periods = $this->period ? [$this->period] : $this->periods;

        return collect($periods)->map(fn($period) => $this->buildSummaryForPeriod($period))->filter()->values()->toArray();
    }

    private function buildSummaryForPeriod(string $period): ?array
    {
        $storeId = $this->storeId;
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
            // Esto incluye todos los imports acumulados (ej: 31 + 31 = 62).
            [$year, $month] = $this->parsePeriod($period);

            // Mantenemos la búsqueda del ID para activar el botón de borrado en la vista
            $latestImportId = Import::where('type', 'operator_report')
                ->where('status', 'completed')
                ->where(function ($query) use ($period) {
                    $query->where('period', $period)
                        ->orWhereHas('operatorReports', fn($q) => $q->where('period_label', $period));
                })
                ->latest()
                ->value('id');

            // Consulta base para todo el periodo
            $periodReportsQuery = OperatorReport::query()
                ->where('period_year', $year)
                ->where('period_month', $month)
                ->where('is_consolidated', false);

            $totalLines = (int) $periodReportsQuery->count();

            $totalCommissionClaro = (float) $periodReportsQuery
                ->selectRaw('COALESCE(SUM(total_commission), SUM(commission_paid_80 + commission_paid_20)) as total')
                ->value('total') ?? 0.0;


            // Hacemos lo mismo para Recargas: buscar por periodo del import O por etiquetas en las recargas
            $latestRechargeImportId = Import::where('type', 'recharge')
                ->where('status', 'completed')
                ->where(function ($query) use ($period) {
                    $query->where('period', $period)
                        ->orWhereHas('recharges', fn($q) => $q->where('period_label', $period));
                })
                ->latest()
                ->value('id');

            if ($latestRechargeImportId) {
                $totalRecargas = (float) Recharge::query()
                    ->where('import_id', $latestRechargeImportId)
                    ->sum('recharge_amount');
            } else {
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

        // CORRECCIÓN: La utilidad es (Lo que Claro paga) - (Lo que pagamos a la tienda)
        // Antes se restaba la 'baseLiquidada' que es el monto de venta bruta, generando negativos falsos.
        $difference = $totalCommissionClaro - $totalPagado;

        // La alerta se dispara si perdemos dinero (diferencia negativa)
        $alert = $difference < 0; // Alerta simple: Si perdemos plata, avisar.

        // El ratio de diferencia para alerta de márgenes bajos (opcional, simplificado por ahora)
        $differenceRate = $totalCommissionClaro > 0 ? ($difference / $totalCommissionClaro) : 0;


        return [
            'period' => $period,
            'store_id' => $storeId,
            'total_lines' => $totalLines,
            'total_commission_claro' => $totalCommissionClaro,
            'total_recargas' => $totalRecargas,
            'base_liquidada' => $baseLiquidada,
            'total_pagado' => $totalPagado, // Nuevo campo
            'difference' => $difference,
            'difference_rate' => $differenceRate,
            'alert' => $alert,
            'status' => $status,
            'operator_import_id' => $latestImportId,
            'recharge_import_id' => $latestRechargeImportId,
        ];
    }

    private function resetPreview(): void
    {
        $this->previewPeriod = null;
        $this->previewStores = [];
        $this->previewStoreId = null;
        $this->previewStoreLines = [];
        $this->selectedStoreIds = [];
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
        $closedLiquidations = Liquidation::with(['items', 'store'])
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->where('status', 'closed')
            ->get();

        foreach ($closedLiquidations as $liq) {
            $storeId = $liq->store_id;
            // Si necesitamos lineas, las cargamos de la relacion. Si no, array vacio para optimizar listados simples (aunque calculatePreviewStores suele pedir con lineas para el cache full).
            // NOTA: Para el preview completo ($withLines=true), necesitamos los items.
            $items = ($withLines && $liq->items) ? $liq->items : collect();

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
                'lines' => $items->map(fn($item) => [
                    'iccid' => $item->iccid,
                    'phone_number' => $item->phone_number,
                    'residual_percentage' => $item->residual_percentage,
                    'movilco_recharge_amount' => $item->movilco_recharge_amount,
                    'base_liquidation_final' => $item->base_liquidation_final,
                    'pago_residual' => $item->final_amount,
                    'status' => 'liquidated'
                ])->toArray(),
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

        $key = 'preview_data_' . Auth::id() . '_' . $this->previewPeriod;
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

        // Bloquear la UI mientras se procesa
        $this->dispatch('liquidation-started');

        $stores = Store::query()
            ->whereIn('id', $storeIds)
            ->get();

        if ($stores->isEmpty()) {
            $this->dispatch('liquidation-finished');
            return;
        }

        $service = app(LiquidationCalculationService::class);
        $currentUser = Auth::guard('admin')->user();
        $period = $this->previewPeriod;
        $successful = 0;
        $failed = [];

        foreach ($stores as $store) {
            try {
                $service->generateLiquidationForStore($store, $period, $currentUser);
                $successful++;
            } catch (\Throwable $e) {
                $failed[] = $store->idpos ?? $store->name ?? (string) $store->id;
            }
        }

        // Limpiar cache
        unset($this->storeStatusCache[$period], $this->storeListCache[$period]);
        $key = 'preview_data_' . Auth::id() . '_' . $period;
        Cache::forget($key);

        // Recalcular y actualizar preview
        $allStores = $this->calculatePreviewStores($period, true);
        Cache::put($key, $allStores, 600);

        $this->updateVisiblePreview();
        $this->selectedStoreIds = $this->collectPendingStoreIds($this->previewStores);
        $this->previewStoreId = null;
        $this->previewStoreLines = [];
        $this->summaries = $this->buildSummaries();

        // Desbloquear la UI
        $this->dispatch('liquidation-finished');

        // Notificaciones
        if ($successful > 0) {
            $message = "Se liquidaron {$successful} tienda(s) correctamente para el período {$period}.";
            if (!empty($failed)) {
                $message .= " Algunas tiendas no pudieron procesarse.";
            }

            Notification::make()
                ->title('✅ Liquidación completada')
                ->body($message)
                ->success()
                ->duration(5000)
                ->send();
        }

        if (!empty($failed)) {
            Notification::make()
                ->title('⚠️ Algunas tiendas no se pudieron liquidar')
                ->body('Revisa: ' . implode(', ', $failed))
                ->danger()
                ->duration(8000)
                ->send();
        }
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

        $key = 'preview_data_' . Auth::id() . '_' . $this->previewPeriod;
        $allStores = collect(Cache::get($key, []));

        // Aplicar filtros de búsqueda actuales a la exportación si se desea, o exportar todo.
        // Asumiremos exportar lo filtrado por búsqueda
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

        $data = $allStores->map(fn($s) => [
            'Código' => $s['idpos'],
            'Nombre' => $s['name'],
            'Estado' => ($s['status'] ?? '') === 'liquidated' ? 'Liquidada' : 'Pendiente',
            'Valor' => $s['total'],
        ]);

        return Excel::download(new class ($data) implements \Maatwebsite\Excel\Concerns\FromCollection, \Maatwebsite\Excel\Concerns\WithHeadings {
            public function __construct(private Collection $data)
            {
            }
            public function collection()
            {
                return $this->data;
            }
            public function headings(): array
            {
                return ['CÓDIGO', 'NOMBRE', 'ESTADO', 'VALOR'];
            }
        }, 'liquidacion-preview-' . ($this->previewPeriod ?? now()->format('Y-m')) . '.xlsx');
    }

    public function exportPreviewDetails()
    {
        // Exportación DETALLADA (Simcard por Simcard)
        if (!$this->previewPeriod)
            return;

        $key = 'preview_data_' . Auth::id() . '_' . $this->previewPeriod;
        $allStores = collect(Cache::get($key, []));

        // Aplicar filtros de búsqueda actuales
        if ($this->previewSearch) {
            $search = strtolower($this->previewSearch);
            $allStores = $allStores->filter(
                fn($s) =>
                str_contains(strtolower($s['name']), $search) ||
                str_contains((string) $s['idpos'], $search)
            );
        }

        // Aplanar los datos: Una fila por cada línea de detalle (simcard)
        $flatData = collect();
        foreach ($allStores as $store) {
            foreach ($store['lines'] as $line) {
                $flatData->push([
                    'Código Tienda' => $store['idpos'],
                    'Nombre Tienda' => $store['name'],
                    'ICCID' => $line['iccid'] ?? 'N/D',
                    'Teléfono' => $line['phone_number'] ?? 'N/D',
                    '% Comisión' => number_format($line['residual_percentage'], 2) . '%',
                    'Recarga Movilco' => $line['movilco_recharge_amount'],
                    'Base Liquidación' => $line['base_liquidation_final'],
                    'Total a Pagar' => $line['pago_residual'],
                ]);
            }
        }

        return Excel::download(new class ($flatData) implements \Maatwebsite\Excel\Concerns\FromCollection, \Maatwebsite\Excel\Concerns\WithHeadings {
            public function __construct(private Collection $data)
            {
            }
            public function collection()
            {
                return $this->data;
            }
            public function headings(): array
            {
                return ['CÓDIGO TIENDA', 'NOMBRE TIENDA', 'ICCID', 'TELÉFONO', '% COMISIÓN', 'RECARGA MOVILCO', 'BASE LIQUIDACIÓN', 'TOTAL A PAGAR'];
            }
        }, 'liquidacion-detalle-' . ($this->previewPeriod ?? now()->format('Y-m')) . '.xlsx');
    }

    private function getStoreStatusSummary(string $period): array
    {
        if (isset($this->storeStatusCache[$period])) {
            return $this->storeStatusCache[$period];
        }

        $stores = $this->calculatePreviewStores($period, false);
        $liquidated = collect($stores)->where('status', 'liquidated')->count();
        $summary = [
            'total' => count($stores),
            'liquidated' => $liquidated,
            'pending' => count($stores) - $liquidated,
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
        } else {
            $this->expandedStoreId = $storeId;
        }
    }
}
