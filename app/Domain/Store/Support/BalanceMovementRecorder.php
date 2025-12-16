<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
declare(strict_types=1);

namespace App\Domain\Store\Support;

use App\Domain\Store\Models\BalanceMovement;
use App\Domain\Store\Models\Liquidation;
use App\Domain\Store\Models\Redemption;
use App\Domain\User\Models\User;
use Illuminate\Support\Carbon;

class BalanceMovementRecorder
{
    public function recordLiquidation(Liquidation $liquidation, ?User $user = null): BalanceMovement
    {
        $liquidation->loadMissing('store');
        $store = $liquidation->store;
        $storeLabel = $store ? "{$store->name} ({$store->idpos})" : "Tienda {$liquidation->store_id}";

        return $this->storeMovement([
            'store_id' => $liquidation->store_id,
            'movement_date' => $liquidation->created_at ?? Carbon::now(),
            'movement_type' => 'credit',
            'operation' => 'liquidation',
            'source_type' => 'liquidation',
            'source_id' => $liquidation->id,
            'description' => sprintf(
                'Liquidacion %02d/%s - %s',
                $liquidation->period_month,
                $liquidation->period_year,
                $storeLabel
            ),
            'amount' => (float) $liquidation->net_amount,
            'status' => 'active',
            'created_by_user_id' => $user?->id,
            'metadata' => [
                'period_year' => $liquidation->period_year,
                'period_month' => $liquidation->period_month,
                'gross_amount' => $liquidation->gross_amount,
                'net_amount' => $liquidation->net_amount,
                'liquidation_status' => $liquidation->status,
                'store_name' => $store?->name,
                'store_idpos' => $store?->idpos,
            ],
        ]);
    }

    public function recordRedemption(Redemption $redemption, ?User $user = null): BalanceMovement
    {
        $redemption->loadMissing('store', 'redemptionProduct');
        $storeLabel = $redemption->store ? "{$redemption->store->name} ({$redemption->store->idpos})" : "Tienda {$redemption->store_id}";

        return $this->storeMovement([
            'store_id' => $redemption->store_id,
            'movement_date' => $redemption->requested_at ?? Carbon::now(),
            'movement_type' => 'debit',
            'operation' => 'redemption',
            'source_type' => 'redemption',
            'source_id' => $redemption->id,
            'description' => 'Redencion: ' . ($redemption->redemptionProduct?->name ?? 'Producto'),
            'amount' => (float) $redemption->total_value,
            'status' => 'active',
            'created_by_user_id' => $user?->id ?? $redemption->handled_by_user_id,
            'metadata' => [
                'redemption_status' => $redemption->status,
                'quantity' => $redemption->quantity,
                'product_id' => $redemption->redemption_product_id,
                'store_name' => $redemption->store->name ?? null,
                'store_idpos' => $redemption->store->idpos ?? null,
            ],
        ]);
    }

    public function recordRefund(
        int $storeId,
        string $description,
        float $amount,
        ?Carbon $date = null,
        ?string $sourceType = 'manual_refund',
        ?int $sourceId = null,
        ?User $user = null
    ): BalanceMovement {
        return $this->storeMovement([
            'store_id' => $storeId,
            'movement_date' => $date ?? Carbon::now(),
            'movement_type' => 'debit',
            'operation' => 'refund',
            'source_type' => $sourceType ?? 'manual_refund',
            'source_id' => $sourceId,
            'description' => $description,
            'amount' => $amount,
            'status' => 'active',
            'created_by_user_id' => $user?->id,
            'metadata' => [],
        ]);
    }

    public function recordAdjustment(
        int $storeId,
        string $description,
        float $amount,
        ?Carbon $date = null,
        ?string $sourceType = 'manual_adjustment',
        ?int $sourceId = null,
        ?User $user = null
    ): BalanceMovement {
        $movementType = $amount < 0 ? 'debit' : 'credit';

        return $this->storeMovement([
            'store_id' => $storeId,
            'movement_date' => $date ?? Carbon::now(),
            'movement_type' => $movementType,
            'operation' => 'adjustment',
            'source_type' => $sourceType ?? 'manual_adjustment',
            'source_id' => $sourceId,
            'description' => $description,
            'amount' => $amount,
            'status' => 'active',
            'created_by_user_id' => $user?->id,
            'metadata' => [],
        ]);
    }

    private function storeMovement(array $data): BalanceMovement
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($data) {

            // Lock de lectura para evitar condiciones de carrera en el saldo
            $lastMovement = BalanceMovement::query()
                ->where('store_id', $data['store_id'])
                ->lockForUpdate()
                ->orderByDesc('movement_date')
                ->orderByDesc('id')
                ->first();

            $lastBalance = $lastMovement ? (float) $lastMovement->balance_after : 0.0;
            $signedAmount = $this->normalizeAmount($data['movement_type'], (float) $data['amount']);
            $newBalance = $lastBalance + $signedAmount;

            $attributes = [
                'store_id' => $data['store_id'],
                'movement_type' => $data['movement_type'],
                'source_type' => $data['source_type'],
                'source_id' => $data['source_id'],
            ];

            $movement = BalanceMovement::firstOrNew($attributes);

            $movement->fill([
                'movement_date' => $data['movement_date'],
                'movement_type' => $data['movement_type'],
                'operation' => $data['operation'] ?? ($movement->operation ?? $data['source_type']),
                'description' => $data['description'],
                'amount' => $signedAmount,
                'balance_after' => $newBalance,
                'status' => $data['status'] ?? 'active',
                'created_by_user_id' => $data['created_by_user_id'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ]);

            $movement->save();

            return $movement;
        });
    }

    private function normalizeAmount(string $movementType, float $amount): float
    {
        $absolute = abs($amount);

        return $movementType === 'debit' ? -1 * $absolute : $absolute;
    }

    private function calculateBalanceAfter(int $storeId, float $signedAmount, ?int $currentId = null): float
    {
        $query = BalanceMovement::query()
            ->where('store_id', $storeId)
            ->where('status', 'active');

        if ($currentId) {
            $query->where('id', '!=', $currentId);
        }

        $lastBalance = $query
            ->orderByDesc('movement_date')
            ->orderByDesc('id')
            ->value('balance_after') ?? 0.0;

        return $lastBalance + $signedAmount;
    }
}
