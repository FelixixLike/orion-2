<?php

declare(strict_types=1);

namespace App\Domain\Import\Services;

use App\Domain\Import\Models\Import;
use App\Domain\Import\Models\OperatorReport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class OperatorReportConsolidatorService
{
    /**
     * Consolida los 4 cortes del operador en un solo registro mensual por simcard/mes.
     * Politica elegida: antes de consolidar un periodo se eliminan (soft) los consolidados previos
     * de ese periodo para evitar duplicados; se mantienen los registros de cortes individuales para auditoria.
     */
    public function consolidate(Import $import): void
    {
        if (empty($import->period)) {
            Log::warning('OperatorReportConsolidatorService: import sin periodo, no se consolida', ['import_id' => $import->id]);
            return;
        }

        [$year, $month] = $this->parsePeriod($import->period);

        // Borramos consolidado previo del mismo periodo (solo los marcados como is_consolidated).
        OperatorReport::query()
            ->where('is_consolidated', true)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->delete();

        // CORRECCIÓN CRÍTICA: Tomamos TODOS los registros crudos del mes, no solo los de este import.
        // Esto permite reconstruir el consolidado acumulativo cuando se suben archivos por partes.
        $rawReports = OperatorReport::query()
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->where('is_consolidated', false)
            ->get();

        if ($rawReports->isEmpty()) {
            Log::info('OperatorReportConsolidatorService: no hay registros para consolidar', ['import_id' => $import->id]);
            return;
        }

        $groups = $rawReports->groupBy(function (OperatorReport $report) {
            if ($report->simcard_id) {
                return 'sim:' . $report->simcard_id;
            }
            if ($report->phone_number) {
                return 'phone:' . $report->phone_number;
            }
            return 'coid:' . ($report->coid ?? $report->id);
        });

        $cutoffDate = Carbon::create($year, $month, 1)->endOfMonth();

        foreach ($groups as $reports) {
            /** @var Collection<int,OperatorReport> $reports */
            $first = $reports->first();

            $commission80 = (float) $reports->sum(fn(OperatorReport $r) => $r->commission_paid_80 ?? 0);
            $commission20 = (float) $reports->sum(fn(OperatorReport $r) => $r->commission_paid_20 ?? 0);
            $totalCommission = $reports->sum(fn(OperatorReport $r) => $r->total_commission ?? 0);

            $consolidated = [
                'simcard_id' => $first->simcard_id,
                'phone_number' => $first->phone_number,
                'city_code' => $first->city_code,
                'coid' => $first->coid,
                'commission_status' => $first->commission_status,
                'activation_date' => $first->activation_date,
                'cutoff_date' => $cutoffDate,
                'commission_paid_80' => $commission80,
                'commission_paid_20' => $commission20,
                'total_commission' => $totalCommission > 0 ? $totalCommission : ($commission80 + $commission20),
                'recharge_amount' => $reports->sum(fn(OperatorReport $r) => $r->recharge_amount ?? 0),
                // Marcamos "M" para indicar consolidado mensual.
                'recharge_period' => 'M',
                'payment_percentage' => $first->payment_percentage,
                'custcode' => $first->custcode,
                'total_recharge_per_period' => $reports->sum(fn(OperatorReport $r) => $r->total_recharge_per_period ?? 0),
                'import_id' => $import->id,
                'period_year' => $year,
                'period_month' => $month,
                'is_consolidated' => true,
                'cutoff_numbers' => $reports
                    ->pluck('recharge_period')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
            ];

            OperatorReport::create($consolidated);
        }
    }

    /**
     * @return array{0:int,1:int}
     */
    private function parsePeriod(string $period): array
    {
        $date = Carbon::createFromFormat('Y-m', $period);

        return [(int) $date->format('Y'), (int) $date->format('m')];
    }
}
