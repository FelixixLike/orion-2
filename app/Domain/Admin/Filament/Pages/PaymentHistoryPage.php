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

        // Ensure the dataset is current before exporting.
        $this->loadData();

        if (empty($this->fullRows)) {
            return null;
        }

        $filename = 'historico-pagos-' . $this->selectedPeriod . '.xlsx';

        return Excel::download(
            new OperatorReportsHistoryExport($this->fullColumns, $this->fullRows),
            $filename
        );
    }

    public function exportRecharges()
    {
        if (empty($this->rechargeRows)) {
            return null;
        }

        $filename = 'recargas-' . ($this->selectedPeriod ?? 'todos') . '.xlsx';

        return Excel::download(
            new \App\Domain\Admin\Exports\RechargesHistoryExport($this->rechargeRows),
            $filename
        );
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

        $rechargePeriods = Import::query()
            ->where('type', ImportType::RECHARGE->value)
            ->where('status', ImportStatus::COMPLETED->value)
            ->whereNotNull('period')
            ->select('period')
            ->distinct()
            ->pluck('period')
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

    private function loadData(): void
    {
        $this->summary = [];
        $this->fullRows = [];
        $this->fullRowsTotal = 0;
        $this->rechargeRows = [];
        $this->rechargeRowsTotal = 0;
        $this->rechargeTotalAmount = 0.0;

        if (empty($this->selectedPeriod)) {
            return;
        }

        $this->loadRechargeData();

        // CAMBIO: Mostrar siempre datos CRUDOS (is_consolidated = false) para que el usuario vea
        // la totalidad de filas subidas (auditoría), incluyendo duplicados de múltiples cargas.
        // La consolidación se usa internamente para liquidar, pero el histórico debe reflejar los inputs.
        $reports = $this->buildReportQuery(false)->get();

        if ($reports->isEmpty()) {
            return;
        }

        $this->hydrateRawPayload($reports);

        $latestImport = $reports->map(fn(OperatorReport $r) => $r->import)->filter()->sortByDesc('created_at')->first();
        $importsCount = $reports->pluck('import_id')->unique()->count();

        $totalPaid = $reports->sum(fn(OperatorReport $r) => (float) ($r->commission_paid_80 ?? 0) + (float) ($r->commission_paid_20 ?? 0));
        $totalCalculated = $reports->sum(function (OperatorReport $r) {
            $percentage = $this->normalizePercentageValue($r->payment_percentage ?? 0);
            $amount = (float) ($r->recharge_amount ?? 0);
            return $amount * $percentage;
        });

        $this->summary = [
            'period' => $this->selectedPeriod,
            'import_id' => $latestImport?->id,
            'imports_count' => $importsCount,
            'created_at' => $latestImport?->created_at?->format('Y-m-d H:i'),
            'total_rows' => $reports->count(),
            'total_paid' => $totalPaid,
            'total_calculated' => $totalCalculated,
            'difference' => $totalPaid - $totalCalculated,
            'cutoff' => $this->summarizeCutoff($reports),
        ];

        $columns = $this->fullColumns;
        $this->fullRows = $reports->map(function (OperatorReport $report) use ($columns) {
            $payload = $report->raw_payload ?? [];
            $row = [];

            foreach ($columns as $column) {
                $key = $column['key'];
                $row[$key] = $payload[$key] ?? null;
            }

            $paid = (float) ($report->commission_paid_80 ?? 0) + (float) ($report->commission_paid_20 ?? 0);
            $calc = $this->calculateFromPayload($payload, $report);

            $row['total_pagado'] = $paid;
            $row['calc_monto_porcentaje'] = $calc;
            $row['diferencia_pago'] = $paid - $calc;
            $row['corte'] = $this->formatCutoffValue($report->recharge_period, $report->import);

            return $row;
        })->toArray();

        $this->fullRowsTotal = count($this->fullRows);
    }

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

        $rechargesQuery = Recharge::query()->with('simcard');

        if ($periodLabel || ($year && $month)) {
            $rechargesQuery->where(function ($query) use ($periodLabel, $year, $month) {
                if ($periodLabel) {
                    $query->where('period_label', $periodLabel);
                }

                if ($year && $month) {
                    $query->orWhere(function ($sub) use ($year, $month) {
                        $sub->whereYear('period_date', $year)
                            ->whereMonth('period_date', $month);
                    });
                }
            });
        }

        $recharges = $rechargesQuery
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->orderByDesc('period_date')
            ->orderBy('id')
            ->get();

        if ($recharges->isEmpty()) {
            return;
        }

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

        $this->rechargeRowsTotal = count($this->rechargeRows);
        $this->rechargeTotalAmount = $recharges->sum(fn(Recharge $recharge) => (float) ($recharge->recharge_amount ?? 0));
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
