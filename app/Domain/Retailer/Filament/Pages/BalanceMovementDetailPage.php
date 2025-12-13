<?php

namespace App\Domain\Retailer\Filament\Pages;

use App\Domain\Store\Models\BalanceMovement;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;

class BalanceMovementDetailPage extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = null;

    protected static ?string $navigationLabel = null;

    protected static ?string $title = 'Detalle del movimiento';

    protected static ?string $slug = 'balance-movement';

    protected string $view = 'filament.retailer.pages.balance-movement-detail-page';

    public array $movement = [];

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(): void
    {
        $user = Auth::guard('retailer')->user();

        if (! $user) {
            abort(403);
        }

        $movementId = request()->integer('movement');

        if (! $movementId) {
            abort(404);
        }

        $movement = BalanceMovement::query()
            ->whereKey($movementId)
            ->where('status', 'active')
            ->firstOrFail();

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

        $this->movement = [
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
    }

    private function signedAmount(BalanceMovement $movement): float
    {
        $amount = abs((float) $movement->amount);

        return $movement->movement_type === 'debit' ? -1 * $amount : $amount;
    }
}
