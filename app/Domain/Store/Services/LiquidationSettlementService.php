<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
declare(strict_types=1);

namespace App\Domain\Store\Services;

use App\Domain\Store\Models\Liquidation;
use App\Domain\Store\Models\Store;
use App\Domain\Store\Support\BalanceMovementRecorder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LiquidationSettlementService
{
    public function __construct(
        private readonly BalanceMovementRecorder $movementRecorder,
    ) {
    }

    /**
     * Genera liquidaciones cerradas por tienda para un periodo y registra movimientos de balance.
     *
     * Nota: La logica de calculo de montos reales se delega a un calculador externo (TODO).
     * Por ahora se usa un stub que retorna 0, y se registra liquidacion solo si el monto es distinto de 0.
     */
    public function generateForPeriod(int $year, int $month, Carbon $crossingDate): void
    {
        $period = sprintf('%04d-%02d', $year, $month);
        $calculator = app(LiquidationCalculationService::class);
        $stores = Store::query()
            ->where('status', 'active')
            ->get(['id', 'name', 'idpos']);

        foreach ($stores as $store) {
            DB::transaction(function () use ($store, $year, $month, $crossingDate) {
                $period = sprintf('%04d-%02d', $year, $month);
                $calculator = app(LiquidationCalculationService::class);
                $calc = $calculator->calculateForStoreAndPeriod($store, $period);
                $netAmount = $calc['total'] ?? 0.0;

                // Si no hay monto, salta. Si se requiere crear en cero, quitar este guard.
                if ($netAmount === 0.0) {
                    return;
                }

                if ($this->existsForPeriod($store->id, $year, $month)) {
                    // Politica elegida: bloquear duplicados por tienda/mes (no se generan versiones automáticas).
                    Log::warning('Liquidacion ya existe, se omite regenerar', [
                        'store_id' => $store->id,
                        'period_year' => $year,
                        'period_month' => $month,
                    ]);
                    return;
                }

                $liquidation = Liquidation::create([
                    'store_id' => $store->id,
                    'period_year' => $year,
                    'period_month' => $month,
                    'version' => 1,
                    'gross_amount' => $netAmount,
                    'net_amount' => $netAmount,
                    'status' => 'closed',
                    'clarifications' => null,
                    'created_at' => $crossingDate,
                    'updated_at' => $crossingDate,
                ]);

                // Registrar movimiento de balance con la fecha del cruce.
                $this->movementRecorder->recordLiquidation(
                    $liquidation,
                    null // usuario admin opcional; se puede pasar Auth::user() si se desea
                );
            });
        }
    }

    /**
     * TODO: Reemplazar por calculador real. Por ahora retorna 0.
     */
    private function getNetForStoreAndPeriod(Store $store, int $year, int $month): float
    {
        // TODO: delegar a calculadora real del cruce.
        return 0.0;
    }

    private function existsForPeriod(int $storeId, int $year, int $month): bool
    {
        return Liquidation::query()
            ->where('store_id', $storeId)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->exists();
    }
}
