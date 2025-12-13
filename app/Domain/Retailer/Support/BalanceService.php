<?php

namespace App\Domain\Retailer\Support;

use App\Domain\Store\Models\BalanceMovement;
use App\Domain\Store\Models\Liquidation;
use App\Domain\Store\Models\Redemption;
use App\Domain\User\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

class BalanceService
{
    /**
     * Calcula saldo disponible para una tienda.
     * Entradas: liquidaciones cerradas (net_amount).
     * Salidas: redenciones aprobadas/entregadas/confirmadas (total_value).
     */
    public function getStoreBalance(int $storeId): float
    {
        if ($storeId <= 0) {
            return 0.0;
        }

        if (Schema::hasTable('balance_movements')) {
            try {
                $movementQuery = BalanceMovement::query()
                    ->where('store_id', $storeId)
                    ->where('status', 'active');

                if ($movementQuery->exists()) {
                    $credits = (float) (clone $movementQuery)
                        ->where('movement_type', 'credit')
                        ->sum('amount');

                    $debits = (float) (clone $movementQuery)
                        ->where('movement_type', 'debit')
                        ->sum('amount');

                    return $credits - abs($debits);
                }
            } catch (QueryException $exception) {
                // Si la tabla no existe en este entorno, continuamos con el fallback legacy.
            }
        }

        // TODO: cuando toda la lógica use balance_movements, eliminar el cálculo basado en liquidaciones/redenciones.
        $entries = (float) Liquidation::query()
            ->where('store_id', $storeId)
            ->where('status', 'closed')
            ->sum('net_amount');

        $exits = (float) Redemption::query()
            ->where('store_id', $storeId)
            ->whereIn('status', ['pending', 'approved', 'delivered'])
            ->sum('total_value');

        return $entries - $exits;
    }

    /**
     * Suma el saldo de todas las tiendas asociadas al usuario (store_user).
     */
    public function getUserTotalBalance(User $user): float
    {
        $storeIds = $user->stores()->pluck('stores.id')->all();

        if (empty($storeIds)) {
            return 0.0;
        }

        if (Schema::hasTable('balance_movements')) {
            try {
                $movementQuery = BalanceMovement::query()
                    ->whereIn('store_id', $storeIds)
                    ->where('status', 'active');

                if ($movementQuery->exists()) {
                    $credits = (float) (clone $movementQuery)
                        ->where('movement_type', 'credit')
                        ->sum('amount');

                    $debits = (float) (clone $movementQuery)
                        ->where('movement_type', 'debit')
                        ->sum('amount');

                    return $credits - abs($debits);
                }
            } catch (QueryException $exception) {
                // Si no hay tabla de movimientos, seguimos con el calculo legacy.
            }
        }

        // TODO: eliminar fallback cuando toda la lógica de saldo use balance_movements.
        $entries = (float) Liquidation::query()
            ->whereIn('store_id', $storeIds)
            ->where('status', 'closed')
            ->sum('net_amount');

        $exits = (float) Redemption::query()
            ->whereIn('store_id', $storeIds)
            ->whereIn('status', ['pending', 'approved', 'delivered'])
            ->sum('total_value');

        return $entries - $exits;
    }
}
