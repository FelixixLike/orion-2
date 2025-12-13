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
    public function calculateForPeriod(string $period): array
    {
        [$year, $month] = $this->parsePeriod($period);

        $reports = OperatorReport::query()
            ->with('simcard')
            ->where('is_consolidated', true)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->whereNull('liquidation_item_id')
            ->get();

        if ($reports->isEmpty()) {
            // Compatibilidad: si no hay consolidado, usar cortes individuales del mes.
            $reports = OperatorReport::query()
                ->with('simcard')
                ->whereYear('cutoff_date', $year)
                ->whereMonth('cutoff_date', $month)
                ->whereNull('liquidation_item_id')
                ->get();
        }

        if ($reports->isEmpty()) {
            return ['stores' => [], 'lines' => []];
        }

        // Obtener la última condición disponible para cada simcard (sin filtro de periodo)
        $latestConditions = $this->latestConditionsBySimcard();

        $recharges = Recharge::query()
            ->where(function ($query) use ($period, $year, $month) {
                $query->where('period_label', $period)
                    ->orWhere(function ($sub) use ($year, $month) {
                        $sub->whereYear('period_date', $year)
                            ->whereMonth('period_date', $month);
                    });
            })
            ->get();

        $rechargeBySimcard = $recharges->groupBy('simcard_id')->map->sum('recharge_amount');

        // Normalizador de teléfonos (últimos 10 dígitos)
        $normalizePhone = fn($p) => substr(preg_replace('/\D/', '', (string) $p), -10);

        $rechargeByPhone = $recharges->groupBy(fn($r) => $normalizePhone($r->phone_number))
            ->map->sum('recharge_amount');

        $storesByIdpos = Store::query()->pluck('id', 'idpos');

        $lines = [];
        $stores = [];

        foreach ($reports as $report) {
            $simcard = $report->simcard;
            $simcardId = $simcard?->id;
            $iccid = $simcard?->iccid;
            $phone = $report->phone_number ?? $simcard?->phone_number;
            $normPhone = $normalizePhone($phone);

            // Usar la última condición disponible para este simcard
            $condition = $latestConditions->get($simcardId);
            // Si no hay condicion, se liquida en 0 residual.

            $idpos = $condition?->idpos;
            $storeId = $idpos ? ($storesByIdpos[$idpos] ?? null) : null;

            $rechargeAmount = (float) ($rechargeBySimcard->get($simcardId, 0.0) ?? 0.0);
            if ($rechargeAmount === 0.0 && $normPhone) {
                // Intentar buscar por teléfono normalizado
                $rechargeAmount = (float) ($rechargeByPhone->get($normPhone, 0.0) ?? 0.0);
            }

            $paymentPercentage = $report->payment_percentage ?? 0;
            if ($paymentPercentage > 1) {
                $paymentPercentage = $paymentPercentage / 100;
            }

            $montoCarga = (float) ($report->recharge_amount ?? 0); // Revertido a recharge_amount para evitar totales globales
            $totalCommission = $report->total_commission ?? (($report->commission_paid_80 ?? 0) + ($report->commission_paid_20 ?? 0));
            $operatorTotalRecharge = $report->total_recharge_per_period; // Mantenemos el dato original para referencia

            // Calculo REAL según usuario: (Ventas - Recargas Movilco) = Base Real
            $baseReal = max($montoCarga - $rechargeAmount, 0); // 12000 - 2000 = 10000
            $discountBase = $baseReal; // Corregido: Definir variable para uso en el array

            $residualPercent = $condition?->commission_percentage ?? 0; // 7%
            $valorSim = $condition?->sale_price ?? null;

            // Pago Residual = Base Real * % Residual
            $pagoResidual = $baseReal * ($residualPercent / 100); // 10000 * 0.07 = 700

            // Variables legacy para compatibilidad si se necesitan, pero la lógica principal es la de arriba
            $paymentPercentage = $report->payment_percentage ?? 0;
            $commissionAfterDiscount = $totalCommission; // Ya no se usa para el calculo principal
            $transferPercent = 0.0; // Ya no aplica el calculo inverso complejo

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
                'recharge_amount' => $rechargeAmount, // Recarga Movilco
                'recharge_discount' => $rechargeAmount,
                'commission_after_discount' => $pagoResidual, // Ajustado para reflejar el valor ganado
                'base_liquidation' => $baseReal,
                'base_liquidation_final' => $baseReal, // Base sobre la cual se calcula (10000)
                'residual_percentage' => $residualPercent,
                'traslado_percentage' => $residualPercent, // Simplificación: Traslado es el mismo residual
                'payment_percentage' => $paymentPercentage,
                'valor_sim' => $valorSim,
                'pago_residual' => $pagoResidual,

                'movilco_recharge_amount' => $rechargeAmount,
                'discount_total_period' => $discountBase,
                'discount_residual' => $rechargeAmount,
                'period' => $period,
                'period_date' => $report->cutoff_date,
                'period_year' => $year,
                'period_month' => $month,
                'sales_condition_id' => $condition?->id,
            ];

            $lines[] = $line;

            if ($storeId) {
                if (!isset($stores[$storeId])) {
                    $stores[$storeId] = [
                        'store_id' => $storeId,
                        'idpos' => $idpos,
                        'total' => 0.0,
                        'lines' => [],
                    ];
                }

                $stores[$storeId]['total'] += $pagoResidual;
                $stores[$storeId]['lines'][] = $line;
            }
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
     */
    private function latestConditionsBySimcard(): Collection
    {
        return SalesCondition::query()
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->orderByDesc('created_at')
            ->get()
            ->unique('simcard_id')
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
