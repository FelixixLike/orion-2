<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Admin\Filament\Pages;

use App\Domain\Import\Models\OperatorReport;
use App\Domain\Store\Models\Liquidation;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Carbon;

class ManagementReportPage extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Reporte gerencial';

    protected static ?string $title = 'Reporte gerencial de comisiones y pagos';

    protected static ?int $navigationSort = 95;

    protected string $view = 'filament.admin.pages.management-report-page';

    public string $period;
    public array $summary = [];
    public array $storeBreakdown = [];

    public static function getSlug(?Panel $panel = null): string
    {
        return 'management-report';
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth('admin')->user();
        return $user?->hasRole(['super_admin', 'administrator', 'management'], 'admin') ?? false;
    }

    public function mount(): void
    {
        $availableOptions = $this->getAvailablePeriodsOptions();
        // Usar el primer periodo disponible (el más reciente) o null si no hay datos
        $defaultPeriod = array_key_first($availableOptions);

        $this->period = request()->get('period', $defaultPeriod ?? '');

        if ($this->period) {
            $this->recomputeReport();
        }

        if (request()->boolean('export') && $this->period) {
            $this->export();
        }
    }

    public function updatedPeriod(): void
    {
        $this->recomputeReport();
    }

    public function refreshReport(): void
    {
        $this->recomputeReport();
        \Filament\Notifications\Notification::make()
            ->title('Reporte actualizado')
            ->success()
            ->send();
    }

    private function recomputeReport(): void
    {
        if (!$this->period) {
            $this->summary = [];
            $this->storeBreakdown = [];
            return;
        }

        $this->summary = $this->buildSummary($this->period);
        $this->storeBreakdown = $this->buildStoreBreakdown($this->period);
    }

    public function exportOrphans()
    {
        if (!$this->period) {
            return;
        }

        [$year, $month] = explode('-', $this->period);
        $year = (int) $year;
        $month = (int) $month;

        $activeSimcardIds = OperatorReport::query()
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->distinct()
            ->pluck('simcard_id');

        $orphanedQuery = \App\Domain\Import\Models\Recharge::query()
            ->where(function ($query) use ($year, $month) {
                $query->where('period_label', $this->period)
                    ->orWhere(function ($sub) use ($year, $month) {
                        $sub->whereYear('period_date', $year)
                            ->whereMonth('period_date', $month);
                    });
            })
            ->whereNotIn('simcard_id', $activeSimcardIds)
            ->with('simcard');

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Domain\Admin\Exports\OrphanedRechargesExport($orphanedQuery->get()),
            "recargas_huerfanas_{$this->period}.xlsx"
        );
    }

    public function getAvailablePeriodsOptions(): array
    {
        // Buscar periodos en Reportes de Operador
        $opPeriods = OperatorReport::query()
            ->selectRaw("CONCAT(period_year, '-', LPAD(period_month::text, 2, '0')) as period_label")
            ->distinct()
            ->pluck('period_label')
            ->toArray();

        // Buscar periodos en Liquidaciones
        $liqPeriods = Liquidation::query()
            ->selectRaw("CONCAT(period_year, '-', LPAD(period_month::text, 2, '0')) as period_label")
            ->distinct()
            ->pluck('period_label')
            ->toArray();

        // Unir y ordenar descendentemente
        $allPeriods = array_unique(array_merge($opPeriods, $liqPeriods));
        rsort($allPeriods);

        if (empty($allPeriods)) {
            return [];
        }

        return array_combine($allPeriods, $allPeriods);
    }

    private function buildSummary(string $period): array
    {
        [$year, $month] = explode('-', $period);
        $year = (int) $year;
        $month = (int) $month;

        $claroTotal = (float) OperatorReport::query()
            ->where('is_consolidated', true)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->sum('total_commission');

        $tenderoTotal = (float) Liquidation::query()
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->sum('net_amount');

        $storesCount = (int) Liquidation::query()
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->distinct('store_id')
            ->count('store_id');

        $difference = $claroTotal - $tenderoTotal;
        $differenceRate = $claroTotal > 0 ? abs($difference) / max($claroTotal, 0.0001) : null;

        return [
            'period' => $period,
            'claro_total' => $claroTotal,
            'tendero_total' => $tenderoTotal,
            'difference' => $difference,
            'stores_count' => $storesCount,
            'difference_rate' => $differenceRate,
        ];
    }

    private function buildStoreBreakdown(string $period): array
    {
        [$year, $month] = explode('-', $period);
        $year = (int) $year;
        $month = (int) $month;

        $liquidations = Liquidation::query()
            ->with(['store:id,name,idpos', 'items:id,liquidation_id,total_commission'])
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->get();

        if ($liquidations->isEmpty()) {
            return [];
        }

        $data = [];

        foreach ($liquidations as $liquidation) {
            $storeId = $liquidation->store_id;
            $storeName = $liquidation->store?->name;
            $storeIdpos = $liquidation->store?->idpos;

            if (!isset($data[$storeId])) {
                $data[$storeId] = [
                    'store_id' => $storeId,
                    'store_label' => trim(($storeIdpos ? "{$storeIdpos} - " : '') . ($storeName ?? 'Tienda')),
                    'claro_total' => 0.0,
                    'tendero_total' => 0.0,
                    'lines' => 0,
                ];
            }

            $data[$storeId]['tendero_total'] += (float) $liquidation->net_amount;
            $data[$storeId]['claro_total'] += (float) $liquidation->items->sum('total_commission');
            $data[$storeId]['lines'] += $liquidation->items->count();
        }

        return collect($data)
            ->map(function (array $row) {
                $difference = $row['claro_total'] - $row['tendero_total'];
                $rate = $row['claro_total'] > 0 ? abs($difference) / max($row['claro_total'], 0.0001) : null;

                $row['difference'] = $difference;
                $row['difference_rate'] = $rate;

                return $row;
            })
            ->sortBy('store_label')
            ->values()
            ->toArray();
    }
}
