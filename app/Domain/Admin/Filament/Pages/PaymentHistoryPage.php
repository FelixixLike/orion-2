<?php

declare(strict_types=1);

namespace App\Domain\Admin\Filament\Pages;

use App\Domain\Admin\Exports\OperatorReportsHistoryExport;
use App\Domain\Import\Enums\ImportStatus;
use App\Domain\Import\Enums\ImportType;
use App\Domain\Import\Models\Import;
use App\Domain\Import\Models\OperatorReport;
use App\Domain\Import\Models\Recharge;
use App\Domain\Import\Support\OperatorReportSchema;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class PaymentHistoryPage extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Histórico Pagos Claro';

    protected static ?string $title = 'Histórico Pagos Claro';

    protected string $view = 'filament.admin.payments.history';

    public array $periodOptions = [];

    public ?string $selectedPeriod = null;

    public array $summary = [];

    public array $fullColumns = [];

    public array $fullRows = [];

    public int $fullRowsTotal = 0;

    public int $fullRowsPerPage = 5;

    public array $perPageOptions = [5, 10, 25, 50, 100];

    public array $rechargeRows = [];

    public int $rechargeRowsTotal = 0;

    public int $rechargeRowsPerPage = 5;

    public float $rechargeTotalAmount = 0.0;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'payments-history';
    }

    public static function getNavigationSort(): ?int
    {
        return 11;
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::guard('admin')->user();
        return $user?->hasAnyRole(['super_admin', 'administrator'], 'admin') ?? false;
    }



    public function mount(): void
    {
        $this->fullColumns = OperatorReportSchema::columns();
        $this->loadPeriods();

        if ($this->selectedPeriod === null && !empty($this->periodOptions)) {
            $this->selectedPeriod = $this->periodOptions[0]['value'];
        }

        $this->loadData();
    }

    public function updatedSelectedPeriod(): void
    {
        $this->loadData();
    }

    public function updatedFullRowsPerPage($value): void
    {
        $value = (int) $value;
        if (!in_array($value, $this->perPageOptions, true)) {
            $value = $this->perPageOptions[0]; // will be 5
        }

        $this->fullRowsPerPage = max(1, $value);
    }

    public function updatedRechargeRowsPerPage($value): void
    {
        $value = (int) $value;
        if (!in_array($value, $this->perPageOptions, true)) {
            $value = $this->perPageOptions[0];
        }

        $this->rechargeRowsPerPage = max(1, $value);
    }

    public function export()
    {
        if (empty($this->selectedPeriod)) {
            return null;
        }

        \App\Jobs\GeneratePaymentHistoryCsv::dispatch(auth()->id(), $this->selectedPeriod, 'full');

        \Filament\Notifications\Notification::make()
            ->title('Exportación iniciada')
            ->body('El archivo se está generando en segundo plano.')
            ->success()
            ->send();

        return null;
    }

    public function exportRecharges()
    {
        if (empty($this->rechargeRows)) {
            // Check if period selected effectively
            if (empty($this->selectedPeriod))
                return null;
        }

        \App\Jobs\GeneratePaymentHistoryCsv::dispatch(auth()->id(), $this->selectedPeriod, 'recharges');

        \Filament\Notifications\Notification::make()
            ->title('Exportación de Recargas iniciada')
            ->body('El archivo se está generando en segundo plano.')
            ->success()
            ->send();

        return null;
    }

    public function exportOrphans()
    {
        if (empty($this->selectedPeriod)) {
            return null;
        }

        [$year, $month] = $this->parseSelectedPeriod();

        if (!$year || !$month) {
            return null;
        }

        // Obtener activeSimcardIds de la misma forma que en loadData.
        // Nota: Esto reutiliza la lógica, pero idealmente deberíamos cachear o refactorizar si es muy pesado.
        // Por ahora, repetimos la consulta para la exportación.

        $activeSimcardIds = $this->buildReportQuery(false)->pluck('simcard_id')->filter()->unique();

        $orphanedQuery = \App\Domain\Import\Models\Recharge::query()
            ->where(function ($query) use ($year, $month) {
                $query->where('period_label', $this->selectedPeriod)
                    ->orWhere(function ($sub) use ($year, $month) {
                        $sub->whereYear('period_date', $year)
                            ->whereMonth('period_date', $month);
                    });
            })
            ->whereNotIn('simcard_id', $activeSimcardIds)
            ->with('simcard');

        return Excel::download(
            new \App\Domain\Admin\Exports\OrphanedRechargesExport($orphanedQuery->get()),
            "recargas_huerfanas_{$this->selectedPeriod}.csv",
            \Maatwebsite\Excel\Excel::CSV
        );
    }

    public function deleteOrphansAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('deleteOrphansAction')
            ->label('Eliminar Definitivamente')
            ->color('gray')
            ->icon('heroicon-m-trash')
            ->size('xs')
            ->requiresConfirmation()
            ->modalHeading('¿Eliminar recargas huérfanas?')
            ->modalDescription('¿Estás seguro de que deseas eliminar estas recargas? Esta acción no se puede deshacer.')
            ->modalSubmitActionLabel('Sí, eliminar')
            ->action(fn() => $this->deleteOrphans());
    }

    public function deleteOrphans()
    {
        if (empty($this->selectedPeriod)) {
            return;
        }

        // Crear registro de proceso en segundo plano
        $process = \App\Domain\Admin\Models\BackgroundProcess::create([
            'user_id' => auth()->id(),
            'type' => 'delete_orphans',
            'name' => "Eliminando recargas huérfanas ({$this->selectedPeriod})",
            'total' => 0,
            'progress' => 0,
            'status' => 'pending',
        ]);

        // Despachar tarea en segundo plano para manejar alto volumen (50k+)
        \App\Domain\Import\Jobs\DeleteOrphanedRechargesJob::dispatch(
            $this->selectedPeriod,
            auth()->id(),
            $process->id
        );

        \Filament\Notifications\Notification::make()
            ->title('Eliminación iniciada')
            ->body("El proceso se está ejecutando en segundo plano. Puedes ver el progreso en la barra flotante.")
            ->info()
            ->send();

        // No recargamos datos inmediatamente porque el job apenas inicia
    }

    private function loadPeriods(): void
    {
        $periodExpr = "COALESCE(operator_reports.period_label, imports.period)";

        $baseQuery = OperatorReport::query()
            ->join('imports', 'imports.id', '=', 'operator_reports.import_id')
            ->where('imports.type', 'operator_report')
            ->where('imports.status', ImportStatus::COMPLETED->value)
            ->where(function ($query) {
                $query->whereNotNull('operator_reports.period_label')
                    ->orWhereNotNull('imports.period');
            })
            ->selectRaw("$periodExpr AS period_label")
            ->selectRaw('COUNT(*) AS total_rows')
            ->selectRaw('COUNT(DISTINCT operator_reports.import_id) AS imports_count')
            ->groupByRaw("$periodExpr, imports.period")
            ->orderByDesc('period_label');

        $stats = (clone $baseQuery)
            ->where('operator_reports.is_consolidated', true)
            ->get();

        if ($stats->isEmpty()) {
            $stats = $baseQuery->get();
        }

        $operatorOptions = $stats->map(fn($stat) => [
            'value' => $stat->period_label,
            'label' => (string) $stat->period_label,
            'imports_count' => $stat->imports_count,
        ])->keyBy('value');

        $rechargePeriods = \App\Domain\Import\Models\Recharge::query()
            ->select('period_label')
            ->distinct()
            ->whereNotNull('period_label')
            ->pluck('period_label')
            ->filter()
            ->values();

        foreach ($rechargePeriods as $period) {
            if (!$operatorOptions->has($period)) {
                $operatorOptions->put($period, [
                    'value' => $period,
                    'label' => $period . ' (sin Pagos Claro, solo recargas)',
                    'imports_count' => 0,
                ]);
            }
        }

        $this->periodOptions = $operatorOptions
            ->values()
            ->sortByDesc('value', SORT_NATURAL)
            ->values()
            ->toArray();
    }

    public bool $isPaginated = true; // Flag for view compatibility
    public int $page = 1;

    public function updatedPage(): void
    {
        $this->loadData();
    }

    public function nextPage(): void
    {
        $this->page++;
        $this->loadData();
    }

    public function previousPage(): void
    {
        if ($this->page > 1) {
            $this->page--;
            $this->loadData();
        }
    }

    private function loadData(): void
    {
        $this->summary = [];
        $this->fullRows = [];
        // Keep fullRowsTotal separate from count($fullRows) because we paginate
        // But reset it here
        if ($this->page < 1)
            $this->page = 1;

        $this->rechargeRows = [];
        $this->rechargeRowsTotal = 0;
        $this->rechargeTotalAmount = 0.0;

        if (empty($this->selectedPeriod)) {
            return;
        }

        $this->loadRechargeData();

        // --- SUMMARY AGGREGATION (SQL Optimized) ---
        $query = $this->buildReportQuery(false); // Base query for period

        // Check availability
        $count = $query->count(); // Fast count (Laravel handles removing order by for count automatically)
        if ($count === 0 && empty($this->rechargeRows)) {
            return;
        }

        $this->fullRowsTotal = $count;

        // Calculate totals in database
        // Fix: Remove order by before aggregating to avoid "must appear in GROUP BY" error
        $aggregates = $query->reorder()->selectRaw('
            SUM(commission_paid_80 + commission_paid_20) as total_paid,
            SUM(recharge_amount) as total_recharge,
            MAX(import_id) as max_import_id,
            COUNT(DISTINCT import_id) as imports_count
        ')->first();

        // Calculate 'calculated' amount directly in SQL to avoid hydrating 25k+ models
        // Logic: IF percentage > 1 THEN percentage/100 ELSE percentage.
        // Use raw query for speed.
        $totalCalculated = $query->sum(DB::raw('recharge_amount * (CASE WHEN payment_percentage > 1 THEN payment_percentage / 100 ELSE payment_percentage END)'));

        // Get latest import details
        $latestImport = null;
        if ($aggregates->max_import_id) {
            $latestImport = Import::find($aggregates->max_import_id);
        } elseif (!empty($this->rechargeRows)) {
            $latestImport = Import::find($this->rechargeRows[0]['import_id'] ?? null);
        }

        $this->summary = [
            'period' => $this->selectedPeriod,
            'import_id' => $latestImport?->id,
            'imports_count' => $aggregates->imports_count ?? 1,
            'created_at' => $latestImport?->created_at?->format('Y-m-d H:i'),
            'total_rows' => $count,
            'total_paid' => (float) $aggregates->total_paid,
            'total_calculated' => $totalCalculated,
            'difference' => ((float) $aggregates->total_paid) - $totalCalculated,
            'cutoff' => 'N/A', // Optimization: skip complex cutoff summary unless needed
            'orphaned_recharges_count' => 0,
            'orphaned_recharges_amount' => 0.0,
        ];

        // Orphans logic (kept same, optimized query exists elsewhere)
        if ($this->selectedPeriod) {
            [$year, $month] = $this->parseSelectedPeriod();
            if ($year && $month) {
                // Re-build query for simcard checking to avoid scope issues or side effects
                $simCheckQuery = $this->buildReportQuery(false);

                // Optimization: Pluck simcard is fast enough for 25k ints
                $activeSimcardIds = $simCheckQuery->pluck('simcard_id')->filter()->unique();

                $orphanedQuery = \App\Domain\Import\Models\Recharge::query()
                    ->where('period_label', $this->selectedPeriod)
                    ->whereNotIn('simcard_id', $activeSimcardIds);

                $this->summary['orphaned_recharges_amount'] = (float) $orphanedQuery->sum('recharge_amount');
                $this->summary['orphaned_recharges_count'] = (int) $orphanedQuery->count();
            }
        }

        // --- PAGINATION FOR ROWS ---
        // Only load the current page slice
        $reportsPage = $query->select('*') // Need all for detail view? Or specific columns?
            ->forPage($this->page, $this->fullRowsPerPage) // Pagination happens in DB
            ->get();

        $this->hydrateRawPayload($reportsPage); // Only hydrate 5-25 items! Secure & Fast.

        $columns = $this->fullColumns;
        $this->fullRows = $reportsPage->map(function ($report) use ($columns) {
            $payload = $report->raw_payload ?? [];
            $row = [];
            foreach ($columns as $column) {
                $row[$column['key']] = $payload[$column['key']] ?? null;
            }

            // Fallback values from DB if payload empty
            if (empty($row['phone_number']))
                $row['phone_number'] = $report->phone_number;
            if (empty($row['iccid']))
                $row['iccid'] = $report->iccid;

            $paid = (float) ($report->commission_paid_80 ?? 0) + (float) ($report->commission_paid_20 ?? 0);
            $calc = $this->calculateExpectedAmount($report->recharge_amount, $report->payment_percentage);

            $row['total_pagado'] = $paid;
            $row['calc_monto_porcentaje'] = $calc;
            $row['diferencia_pago'] = $paid - $calc;
            $row['corte'] = $this->formatCutoffValue($report->recharge_period, $report->import);

            return $row;
        })->toArray();
    }

    private function calculateExpectedAmount($amount, $percentage): float
    {
        $amountValue = (float) $amount;
        $percentageValue = $this->normalizePercentageValue($percentage);
        return $amountValue * $percentageValue;
    }

    // Keep hydrateRawPayload but it will only process the small collection passed to it


    private function hydrateRawPayload(Collection $reports): void
    {
        $groups = $reports
            ->filter(fn(OperatorReport $report) => empty($report->raw_payload))
            ->groupBy('import_id');

        if ($groups->isEmpty()) {
            return;
        }

        $imports = Import::query()
            ->whereIn('id', $groups->keys())
            ->get()
            ->keyBy('id');

        foreach ($groups as $importId => $entries) {
            $import = $imports->get($importId);
            if (!$import || empty($import->file) || !Storage::disk('local')->exists($import->file)) {
                continue;
            }

            $rawRows = $this->extractRowsFromFile(Storage::disk('local')->path($import->file));
            if (empty($rawRows)) {
                continue;
            }

            $coidKey = OperatorReportSchema::normalizeHeader('COID');
            $iccidKey = OperatorReportSchema::normalizeHeader('ICCID');
            $rowsByCoid = [];

            foreach ($rawRows as $row) {
                $normalized = OperatorReportSchema::normalizeRow($row);

                // CRÍTICO: Limpiar el ICCID si existe (quitar primeros 2 y último dígito)
                if (isset($normalized[$iccidKey]) && !empty($normalized[$iccidKey])) {
                    $iccidRaw = (string) $normalized[$iccidKey];
                    $iccidCleaned = \App\Domain\Import\Services\IccidCleanerService::clean($iccidRaw);
                    if ($iccidCleaned) {
                        $normalized[$iccidKey] = $iccidCleaned;
                    }
                }

                $coid = (string) ($normalized[$coidKey] ?? '');
                if ($coid !== '') {
                    $rowsByCoid[$coid] = $normalized;
                }
            }


            foreach ($entries as $report) {
                $coid = (string) ($report->coid ?? '');
                if ($coid !== '' && isset($rowsByCoid[$coid])) {
                    $report->raw_payload = $rowsByCoid[$coid];
                    $report->save();
                }
            }
        }
    }

    /**
     * @return array<int,array<int|string,mixed>>
     */
    private function extractRowsFromFile(string $filePath): array
    {
        try {
            $sheets = Excel::toArray(new \stdClass(), $filePath);
        } catch (\Throwable $e) {
            return [];
        }

        foreach ($sheets as $sheet) {
            if (empty($sheet) || count($sheet) < 2) {
                continue;
            }

            $headers = $sheet[0];
            if (!OperatorReportSchema::isOperatorSheet($headers)) {
                continue;
            }

            $rows = [];
            foreach (array_slice($sheet, 1) as $row) {
                if ($this->rowIsEmpty($row)) {
                    continue;
                }

                $assoc = [];
                foreach ($headers as $index => $header) {
                    $label = is_string($header) ? $header : 'column_' . $index;
                    $assoc[$label] = $row[$index] ?? null;
                }

                $rows[] = $assoc;
            }

            if (!empty($rows)) {
                return $rows;
            }
        }

        return [];
    }

    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function calculateFromPayload(array $payload, OperatorReport $report): float
    {
        $amountKey = OperatorReportSchema::normalizeHeader('MONTO_CARGA');
        $percentageKey = OperatorReportSchema::normalizeHeader('PORCENTAJE_PAGO');

        $amount = $payload[$amountKey] ?? $report->recharge_amount ?? 0;
        $percentage = $payload[$percentageKey] ?? $report->payment_percentage ?? 0;

        $amountValue = $this->toFloat($amount);
        $percentageValue = $this->normalizePercentageValue($percentage);

        return $amountValue * $percentageValue;
    }

    private function toFloat(mixed $value): float
    {
        if ($value instanceof \DateTimeInterface) {
            return (float) $value->format('U');
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $cleaned = str_replace(['.', ' ', ','], ['', '', '.'], $value);
            return is_numeric($cleaned) ? (float) $cleaned : 0.0;
        }

        return 0.0;
    }

    private function normalizePercentageValue(mixed $value): float
    {
        $numeric = $this->toFloat($value);

        if ($numeric > 1) {
            return $numeric / 100;
        }

        return $numeric;
    }

    private function formatCutoffValue(?string $value, ?Import $import = null): string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '' && $import) {
            $raw = (string) ($import->cutoff_number ?? '');
        }

        if ($raw === '' || $raw === '0') {
            return 'Mensual';
        }

        if (strtoupper($raw) === 'M') {
            return 'Mensual';
        }

        return 'Corte ' . strtoupper($raw);
    }

    private function summarizeCutoff(Collection $reports): string
    {
        $values = $reports
            ->map(fn(OperatorReport $report) => $this->formatCutoffValue($report->recharge_period, $report->import))
            ->unique()
            ->values();

        if ($values->count() === 1) {
            return $values->first();
        }

        return 'Múltiples';
    }

    private function loadRechargeData(): void
    {
        [$year, $month] = $this->parseSelectedPeriod();

        if (!$year || !$month) {
            return;
        }

        $periodLabel = $this->selectedPeriod;

        $baseQuery = Recharge::query();

        if ($periodLabel || ($year && $month)) {
            $baseQuery->where(function ($query) use ($periodLabel, $year, $month) {
                if ($periodLabel) {
                    $query->where('period_label', $periodLabel);
                }

                if ($year && $month) {
                    $query->orWhere(function ($sub) use ($year, $month) {
                        $sub->where('period_year', $year)
                            ->where('period_month', $month);
                    });
                }
            });
        }

        // 1. Calculate Count and Total directly in SQL (Fast)
        $this->rechargeRowsTotal = $baseQuery->count();
        $this->rechargeTotalAmount = (float) $baseQuery->sum('recharge_amount');

        if ($this->rechargeRowsTotal === 0) {
            return;
        }

        // 2. Fetch only the current page slice (Fast)
        // Note: Using page 1 logic unless we want separate pagination for recharges.
        // Assuming user just wants to see "some" recharges or we use $this->page if shared?
        // Usually recharges table has its own pagination or is just a list.
        // Given existing code uses $this->rechargeRowsPerPage, lets use that.
        // But $this->page is shared with main table? If so, paging main table pages recharges too.
        // Let's assume shared pagination for now as per current structure.

        $recharges = $baseQuery
            ->with(['simcard']) // Eager load simcard relation
            ->orderByDesc('id') // Determinisic order
            ->forPage($this->page, $this->rechargeRowsPerPage)
            ->get();

        $this->rechargeRows = $recharges->map(function (Recharge $recharge) {
            $label = $recharge->period_label;
            if (!$label && $recharge->period_year && $recharge->period_month) {
                $label = sprintf('%04d-%02d', $recharge->period_year, $recharge->period_month);
            }
            if (!$label && $recharge->period_date) {
                $label = $recharge->period_date->format('Y-m');
            }

            return [
                'iccid' => $recharge->simcard?->iccid,
                'phone_number' => $recharge->phone_number,
                'recharge_amount' => (float) ($recharge->recharge_amount ?? 0),
                'period_date' => optional($recharge->period_date)->format('Y-m-d'),
                'period_label' => $label,
                'import_id' => $recharge->import_id,
            ];
        })->toArray();
    }

    /**
     * @return array{0:int|null,1:int|null}
     */
    private function parseSelectedPeriod(): array
    {
        if (empty($this->selectedPeriod) || !str_contains($this->selectedPeriod, '-')) {
            return [null, null];
        }

        [$year, $month] = explode('-', $this->selectedPeriod);

        $year = is_numeric($year) ? (int) $year : null;
        $month = is_numeric($month) ? (int) $month : null;

        if (!$year || !$month || $month < 1 || $month > 12) {
            return [null, null];
        }

        return [$year, $month];
    }

    private function buildReportQuery(bool $onlyConsolidated = false): Builder
    {
        // FIX: Evitar JOIN explícito para prevenir duplicación cartesiana y errores de "DISTINCT JSON".
        // Usamos lógica de Eloquent para filtrar.

        $query = OperatorReport::query()
            ->with(['simcard', 'import']);

        // Filtro principal: Import debe ser tipo operator y completed
        $query->whereHas('import', function ($q) {
            $q->where('type', 'operator_report')
                ->where('status', ImportStatus::COMPLETED->value);
        });

        // Filtro de periodo: O el reporte tiene el label, O su import tiene el periodo
        $query->where(function ($q) {
            $q->where('period_label', $this->selectedPeriod)
                ->orWhereHas('import', function ($sq) {
                    $sq->where('period', $this->selectedPeriod);
                });
        });

        if ($onlyConsolidated) {
            $query->where('is_consolidated', true);
        } else {
            $query->where('is_consolidated', false);
        }

        return $query
            ->orderBy('cutoff_date')
            ->orderBy('id');
    }
}
