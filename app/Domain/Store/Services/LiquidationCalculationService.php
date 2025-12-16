<?php

declare(strict_types=1);


/**
 * @author Andrés Felipe Martínez González <felixix-like@outlook.es>
 * @copyright 2025 Derechos Reservados. Uso exclusivo bajo licencia privada.
 */

namespace App\Domain\Store\Services;

use App\Domain\Import\Models\OperatorReport;
use App\Domain\Import\Models\Recharge;
use App\Domain\Import\Models\SalesCondition;
use App\Domain\Import\Models\Simcard;
use App\Domain\Store\Models\Liquidation;
use App\Domain\Store\Models\LiquidationItem;
use App\Domain\Store\Models\Store;
use App\Domain\Store\Support\BalanceMovementRecorder;
use App\Domain\User\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LiquidationCalculationService
{
    /**
     * Calcula las lineas de liquidacion para todas las tiendas en un periodo.
     *
     * @param string $period Periodo en formato 'YYYY-MM'.
     * @return array{
     *     stores: array<int, array{
     *         store_id: int,
     *         idpos: string|null,
     *         total: float,
     *         lines: array<int, array<string,mixed>>
     *     }>,
     *     lines: array<int, array<string,mixed>>
     * }
     */
    public function calculateForPeriod(string $period, ?callable $onProgress = null, array $limitReportIds = []): array
    {
        [$year, $month] = $this->parsePeriod($period);

        // REVERTIDO A LOGICA STANDARD (LEGACY) POR PETICION DE USUARIO
        // Se mantiene eager loading para rendimiento, pero volvemos a iterar en PHP.

        $query = OperatorReport::query()
            ->with(['simcard']) // Cargar relación
            ->where('is_consolidated', true)
            ->where(function ($q) use ($year, $month, $period) {
                $q->where(function ($sub) use ($year, $month) {
                    $sub->where('period_year', $year)->where('period_month', $month);
                })
                    ->orWhere('period_label', $period)
                    ->orWhere(function ($sub) use ($year, $month) {
                        $sub->whereNull('period_year')
                            ->whereYear('cutoff_date', $year)
                            ->whereMonth('cutoff_date', $month);
                    });
            })
            ->whereNull('liquidation_item_id');

        if (!empty($limitReportIds)) {
            $query->whereIn('id', $limitReportIds);
        }

        $reports = $query->get();

        if ($reports->isEmpty()) {
            return ['stores' => [], 'lines' => []];
        }

        // Obtener la última condición disponible para cada simcard (optimizado)
        // Usamos la colección en memoria para sacar IDs
        $simIds = $reports->pluck('simcard_id')->filter()->unique();
        $latestConditions = $this->latestConditionsBySimcard(); // Método existente

        // Cargar Recargas - Lógica original
        $recharges = Recharge::query()
            ->where(function ($query) use ($period, $year, $month) {
                $query->where('period_label', $period)
                    ->orWhere(function ($sub) use ($year, $month) {
                        $sub->whereYear('period_date', $year)
                            ->whereMonth('period_date', $month);
                    });
            })
            ->get();

        $rechargeBySimcard = $recharges->whereNotNull('simcard_id')->groupBy('simcard_id')->map->sum('recharge_amount');

        // Normalizador
        $normalizePhone = fn($p) => substr(preg_replace('/\D/', '', (string) $p), -10);
        $rechargeByPhone = $recharges->groupBy(fn($r) => $normalizePhone($r->phone_number))
            ->map->sum('recharge_amount');

        // Mapa de tiendas
        $storesByIdpos = Store::pluck('id', 'idpos')->toArray();

        // Mapa de Simcards por Teléfono para reportes sin vinculo directo
        $simIdsByPhone = [];
        $reportsWithoutSim = $reports->whereNull('simcard_id')->whereNotNull('phone_number');

        if ($reportsWithoutSim->isNotEmpty()) {
            $phonesToResolve = $reportsWithoutSim->pluck('phone_number')->filter()->unique()->values();
            if ($phonesToResolve->isNotEmpty()) {
                $simIdsByPhone = Simcard::whereIn('phone_number', $phonesToResolve)
                    ->pluck('id', 'phone_number')
                    ->toArray();
            }
        }

        $lines = [];
        $stores = [];

        $totalReports = $reports->count();
        $processedCount = 0;

        foreach ($reports as $report) {
            $processedCount++;
            if ($onProgress && $processedCount % 500 === 0) {
                $onProgress($processedCount, $totalReports);
            }
            $simcard = $report->simcard;
            $simcardId = $simcard?->id;

            $phone = $report->phone_number ?? $simcard?->phone_number;

            // Si no hay simcard, intentamos recuperar por el ID que tenga el reporte
            if (!$simcardId) {
                $simcardId = $report->simcard_id;
            }

            // Fallback: Buscar por teléfono si aún no tenemos simcardId
            if (!$simcardId && $phone && isset($simIdsByPhone[$phone])) {
                $simcardId = $simIdsByPhone[$phone];
            }

            $phone = $report->phone_number ?? $simcard?->phone_number;
            $iccid = $simcard?->iccid ?? $report->iccid;

            $condition = $latestConditions->get($simcardId);
            $idpos = $condition?->idpos;
            $storeId = $idpos ? ($storesByIdpos[$idpos] ?? null) : null;

            // Logica de montos
            $rechargeAmount = (float) ($rechargeBySimcard->get($simcardId, 0.0) ?? 0.0);
            if ($rechargeAmount === 0.0 && $phone) {
                $rechargeAmount = (float) ($recharges->where('phone_number', $phone)->sum('recharge_amount'));
            }
            if ($rechargeAmount === 0.0 && $phone) {
                $norm = $normalizePhone($phone);
                $rechargeAmount = (float) ($rechargeByPhone->get($norm, 0.0) ?? 0.0);
            }

            $montoCarga = (float) ($report->recharge_amount ?? 0);
            $totalCommission = $report->total_commission ?? (($report->commission_paid_80 ?? 0) + ($report->commission_paid_20 ?? 0));
            $operatorTotalRecharge = $report->total_recharge_per_period;

            // Base Real
            $baseReal = max($montoCarga - $rechargeAmount, 0);
            $residualPercent = $condition?->commission_percentage ?? 0;
            $pagoResidual = $baseReal * ($residualPercent / 100);

            $line = [
                'operator_report_id' => $report->id,
                'simcard_id' => $simcardId,
                'iccid' => $iccid,
                'phone_number' => $phone,
                'idpos' => $idpos,
                'store_id' => $storeId,
                'total_commission' => $totalCommission,
                'commission_status' => $report->commission_status,
                'activation_date' => $report->activation_date,
                'cutoff_date' => $report->cutoff_date,
                'custcode' => $report->custcode,
                'operator_amount' => $operatorTotalRecharge,
                'operator_total_recharge' => $operatorTotalRecharge,
                'recharge_amount' => $rechargeAmount,
                'recharge_discount' => $rechargeAmount,
                'commission_after_discount' => $pagoResidual,
                'base_liquidation' => $baseReal,
                'base_liquidation_final' => $baseReal,
                'residual_percentage' => $residualPercent,
                'traslado_percentage' => $residualPercent,
                'payment_percentage' => $report->payment_percentage,
                'valor_sim' => $condition?->sale_price,
                'pago_residual' => $pagoResidual,

                'movilco_recharge_amount' => $rechargeAmount,
                'discount_total_period' => $baseReal, // Alias
                'discount_residual' => $rechargeAmount, // Alias

                'period' => $period,
                'period_date' => $report->cutoff_date,
                'period_year' => $year,
                'period_month' => $month,
                'sales_condition_id' => $condition?->id,
            ];

            $lines[] = $line;

            // Agrupar líneas huérfanas en una tienda virtual ID -1 para no perder el dinero
            $targetStoreId = $storeId ?: -1;

            if (!isset($stores[$targetStoreId])) {
                $stores[$targetStoreId] = [
                    'store_id' => $targetStoreId,
                    'idpos' => $idpos ?: 'SIN-VINCULO',
                    'name' => $storeId ? 'Store' : 'SIN TIENDA ASIGNADA', // Se rellenará bien en el Job Finalizer si es real
                    'total' => 0.0,
                    'lines' => [],
                ];
            }
            $stores[$targetStoreId]['total'] += $pagoResidual;
            $stores[$targetStoreId]['lines'][] = $line;
        }

        return ['stores' => $stores, 'lines' => $lines];
    }

    /**
     * Calcula y genera la liquidacion en borrador para una tienda y periodo.
     *
     * @param Store $store
     * @param string $period Periodo 'YYYY-MM'
     */
    public function generateLiquidationForStore(Store $store, string $period, ?User $user = null): Liquidation
    {

        [$year, $month] = $this->parsePeriod($period);

        $storeLines = $this->calculateForStoreAndPeriod($store, $period);



        if ($storeLines['lines']->isEmpty()) {

            throw new \Exception("No hay lineas para liquidar en {$period} para la tienda {$store->idpos}");

        }



        $alreadyClosed = Liquidation::query()
            ->where('store_id', $store->id)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->where('status', 'closed')
            ->exists();

        if ($alreadyClosed) {
            throw new \Exception("La tienda {$store->idpos} ya tiene liquidación cerrada en {$period}");
        }

        $liquidation = DB::transaction(function () use ($store, $storeLines, $year, $month, $period, $user) {

            $existing = Liquidation::query()

                ->where('store_id', $store->id)

                ->where('period_year', $year)

                ->where('period_month', $month)

                ->where('status', '!=', 'closed')

                ->first();



            if ($existing) {

                $this->releaseOperatorReports($existing);

                $existing->items()->delete();

                $existing->version = ($existing->version ?? 1) + 1;

                $existing->status = 'draft';

                $existing->save();

                $liquidation = $existing;

            } else {

                $liquidation = Liquidation::create([

                    'store_id' => $store->id,

                    'period_year' => $year,

                    'period_month' => $month,

                    'version' => 1,

                    'gross_amount' => 0,

                    'net_amount' => 0,

                    'status' => 'draft',

                    'clarifications' => null,

                    'created_by' => $user?->id,

                ]);

            }



            $total = 0;



            foreach ($storeLines['lines'] as $line) {

                $item = $liquidation->items()->create([

                    'simcard_id' => $line['simcard_id'],

                    'phone_number' => $line['phone_number'],

                    'iccid' => $line['iccid'],

                    'commission_status' => $line['commission_status'],

                    'activation_date' => $line['activation_date'],

                    'cutoff_date' => $line['cutoff_date'],

                    'custcode' => $line['custcode'],

                    'operator_report_id' => $line['operator_report_id'],

                    'sales_condition_id' => $line['sales_condition_id'] ?? null,

                    'total_commission' => $line['total_commission'],

                    'operator_total_recharge' => $line['operator_total_recharge'],

                    'movilco_recharge_amount' => $line['movilco_recharge_amount'],

                    'discount_total_period' => $line['discount_total_period'],

                    'discount_residual' => $line['discount_residual'],

                    'base_liquidation_final' => $line['base_liquidation_final'],

                    'recharge_discount' => $line['recharge_discount'],

                    'commission_after_discount' => $line['commission_after_discount'],

                    'liquidation_multiplier' => ($line['traslado_percentage'] ?? 0) / 100,

                    'final_amount' => $line['pago_residual'],

                    'period_date' => $line['period_date'],

                    'period' => $period,

                    'liquidation_month' => $period,

                    'sim_value' => $line['valor_sim'],

                    'residual_percentage' => $line['residual_percentage'],

                    'transfer_percentage' => $line['traslado_percentage'],

                    'residual_payment' => $line['pago_residual'],

                    'idpos' => $line['idpos'],

                    'created_by' => $user?->id,

                ]);



                $total += (float) $line['pago_residual'];



                OperatorReport::where('id', $line['operator_report_id'])

                    ->update(['liquidation_item_id' => $item->id]);

            }



            $liquidation->update([

                'gross_amount' => $total,

                'net_amount' => $total,

                'status' => 'closed',

            ]);



            return $liquidation->load('items', 'store');

        });

        app(BalanceMovementRecorder::class)->recordLiquidation($liquidation, $user);

        return $liquidation;

    }



    /**
     * Compatibilidad: calcula una simcard por ano/mes (legacy).
     */
    public function calculateForSimcard(int $simcardId, int $periodYear, int $periodMonth): ?array
    {
        $period = sprintf('%04d-%02d', $periodYear, $periodMonth);
        $store = $this->guessStoreBySimcard($simcardId);
        if (!$store) {
            return null;
        }

        $result = $this->calculateForStoreAndPeriod($store, $period);

        return $result['lines']->firstWhere('simcard_id', $simcardId) ?? null;
    }

    /**
     * Calcula las lineas de liquidacion para una tienda en un periodo.
     *
     * @return array{
     *     lines: \Illuminate\Support\Collection,
     *     total: float
     * }
     */
    public function calculateForStoreAndPeriod(Store $store, string $period): array
    {
        $calculations = $this->calculateForPeriod($period);
        $storeLines = $calculations['stores'][$store->id] ?? ['lines' => [], 'total' => 0];

        return [
            'lines' => collect($storeLines['lines']),
            'total' => $storeLines['total'],
        ];
    }

    /**
     * Obtiene las simcards con reportes pendientes de liquidar para una tienda.
     * Una simcard se considera pendiente cuando tiene operator_reports sin liquidation_item_id.
     *
     * No modifica liquidaciones ni datos existentes: solo consulta.
     *
     * @return \Illuminate\Support\Collection<int, Simcard>
     */
    public function getPendingSimcardsForStore(Store $store): Collection
    {
        if (!$store->idpos) {
            return collect();
        }

        return Simcard::query()
            ->select('simcards.*')
            ->join('sales_conditions', 'sales_conditions.simcard_id', '=', 'simcards.id')
            ->join('operator_reports', 'operator_reports.simcard_id', '=', 'simcards.id')
            ->where('sales_conditions.idpos', $store->idpos)
            ->whereNull('operator_reports.liquidation_item_id')
            ->distinct('simcards.id')
            ->get();
    }

    /**
     * Obtiene las simcards que ya fueron liquidadas para una tienda.
     * Una simcard se considera liquidada cuando tiene operator_reports con liquidation_item_id no nulo.
     *
     * @return \Illuminate\Support\Collection<int, Simcard>
     */
    public function getLiquidatedSimcardsForStore(Store $store): Collection
    {
        if (!$store->idpos) {
            return collect();
        }

        return Simcard::query()
            ->select('simcards.*')
            ->join('sales_conditions', 'sales_conditions.simcard_id', '=', 'simcards.id')
            ->join('operator_reports', 'operator_reports.simcard_id', '=', 'simcards.id')
            ->where('sales_conditions.idpos', $store->idpos)
            ->whereNotNull('operator_reports.liquidation_item_id')
            ->distinct('simcards.id')
            ->get();
    }

    private function parsePeriod(string $period): array
    {
        $date = Carbon::createFromFormat('Y-m', $period);
        return [(int) $date->format('Y'), (int) $date->format('m')];
    }

    private function conditionsForPeriod(int $year, int $month): Collection
    {
        return SalesCondition::query()
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->get()
            ->keyBy('simcard_id');
    }

    private function previousConditionsBySimcard(int $year, int $month): Collection
    {
        return SalesCondition::query()
            ->where(function ($query) use ($year, $month) {
                $query->where('period_year', '<', $year)
                    ->orWhere(function ($q) use ($year, $month) {
                        $q->where('period_year', $year)->where('period_month', '<', $month);
                    });
            })
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->get()
            ->unique('simcard_id')
            ->keyBy('simcard_id');
    }

    /**
     * Obtiene la última condición comercial disponible para cada simcard.
     * Las condiciones son permanentes por ICCID, no por periodo.
     * OPTIMIZADO: Usa window functions de PostgreSQL para evitar cargar 23k+ registros en memoria.
     */
    private function latestConditionsBySimcard(): Collection
    {
        return SalesCondition::query()
            ->fromSub(function ($query) {
                $query->selectRaw('
                    *,
                    ROW_NUMBER() OVER (
                        PARTITION BY simcard_id 
                        ORDER BY period_year DESC, period_month DESC, created_at DESC
                    ) as rn
                ')
                    ->from('sales_conditions');
            }, 'ranked')
            ->where('rn', 1)
            ->get()
            ->keyBy('simcard_id');
    }

    private function resolveConditionForSim(?int $simcardId, Collection $periodConditions, Collection $fallbackConditions): ?SalesCondition
    {
        if (!$simcardId) {
            return null;
        }

        if ($periodConditions->has($simcardId)) {
            return $periodConditions->get($simcardId);
        }

        return $fallbackConditions->get($simcardId);
    }

    private function guessStoreBySimcard(int $simcardId): ?Store
    {
        /** @var Simcard|null $simcard */
        $simcard = Simcard::find($simcardId);
        if (!$simcard) {
            return null;
        }

        $idpos = SalesCondition::where('simcard_id', $simcard->id)
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->orderByDesc('period_date')
            ->value('idpos');

        if (!$idpos) {
            return null;
        }

        return Store::where('idpos', $idpos)->first();
    }

    private function releaseOperatorReports(Liquidation $liquidation): void
    {
        $operatorReportIds = $liquidation->items()
            ->whereNotNull('operator_report_id')
            ->pluck('operator_report_id')
            ->all();

        if (!empty($operatorReportIds)) {
            OperatorReport::whereIn('id', $operatorReportIds)->update(['liquidation_item_id' => null]);
        }
    }
}
