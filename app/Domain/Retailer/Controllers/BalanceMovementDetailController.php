<?php

namespace App\Domain\Retailer\Controllers;

use App\Domain\Store\Models\BalanceMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;

class BalanceMovementDetailController
{
    public function __invoke(Request $request, BalanceMovement $movement)
    {
        $user = Auth::guard('retailer')->user();

        if (! $user) {
            abort(403);
        }

        $storeIds = $user->stores()->pluck('stores.id')->all();

        if (! in_array($movement->store_id, $storeIds, true)) {
            abort(403);
        }

        $amount = $this->signedAmount($movement);

        $operation = $movement->operation ?? $movement->source_type ?? $movement->movement_type;

        $operationLabels = [
            'liquidation' => 'Liquidacion',
            'redemption' => 'Redencion',
            'refund' => 'Devolucion',
            'adjustment' => 'Ajuste',
        ];

        $typeLabel = $operationLabels[$operation] ?? ucfirst($operation ?? 'Ajuste');

        $store = $movement->store;

        $viewMovement = [
            'id' => $movement->id,
            'date' => optional($movement->movement_date)->format('Y-m-d'),
            'type' => $movement->movement_type,
            'type_label' => $typeLabel,
            'description' => $movement->description ?: $typeLabel,
            'amount' => $amount,
            'formatted_amount' => Number::currency($amount, 'COP'),
            'status' => $movement->status,
            'status_label' => ucfirst($movement->status ?? ''),
            'store_name' => $store?->name,
            'idpos' => $store?->idpos,
            'export_url' => route('portal.movement.export', ['movement' => $movement->id]),
        ];

        return view('filament.retailer.pages.balance-movement-detail-page', [
            'movement' => $viewMovement,
        ]);
    }

    private function signedAmount(BalanceMovement $movement): float
    {
        $amount = abs((float) $movement->amount);

        return $movement->movement_type === 'debit' ? -1 * $amount : $amount;
    }
}

