<?php

namespace App\Domain\Store\Jobs;

use App\Domain\Store\Models\Liquidation;
use App\Domain\Store\Models\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FinalizeLiquidationPreviewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $period,
        public string $baseResultKey,
        public int $totalChunks,
        public string $finalResultKey,
        public string $progressKey
    ) {
    }

    public function handle(): void
    {
        Log::info("Finalizing liquidation preview for {$this->period} with {$this->totalChunks} chunks.");

        $mergedStores = [];

        // Merge Chunks
        for ($i = 0; $i < $this->totalChunks; $i++) {
            $key = $this->baseResultKey . '_' . $i;
            $chunk = Cache::get($key);

            if (!$chunk) {
                Log::warning("Missing chunk {$i} for key {$this->baseResultKey}");
                continue;
            }

            foreach ($chunk['stores'] as $storeId => $data) {
                if (!isset($mergedStores[$storeId])) {
                    $mergedStores[$storeId] = $data;
                } else {
                    $mergedStores[$storeId]['total'] += $data['total'];
                    $mergedStores[$storeId]['lines'] = array_merge($mergedStores[$storeId]['lines'], $data['lines']);
                }
            }

            Cache::forget($key);
        }

        $formatted = $this->formatResults($mergedStores, $this->period);

        Cache::put($this->finalResultKey, $formatted, 3600);
        Cache::put($this->progressKey, 100, 3600);
    }

    private function formatResults(array $pendingStoresRaw, string $period): array
    {
        [$year, $month] = explode('-', $period);
        $finalStoresMap = [];

        // 1. OBTENER LIQUIDACIONES CERRADAS
        $closedLiquidations = Liquidation::query()
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->where('status', 'closed')
            ->with(['items', 'store'])
            ->get();

        foreach ($closedLiquidations as $liq) {
            $storeId = $liq->store_id;
            $items = $liq->items;
            $finalStoresMap[$storeId] = [
                'store_id' => $storeId,
                'name' => $liq->store?->name ?? 'Tienda Liquidada',
                'idpos' => $liq->store?->idpos ?? 'N/D',
                'total' => (float) $items->sum('final_amount'),
                'status' => 'liquidated',
                'lines' => $items->map(fn($item) => [
                    'iccid' => $item->iccid,
                    'phone_number' => $item->phone_number,
                    'residual_percentage' => $item->residual_percentage,
                    'movilco_recharge_amount' => $item->movilco_recharge_amount,
                    'base_liquidation_final' => $item->base_liquidation_final,
                    'pago_residual' => $item->final_amount,
                    'status' => 'liquidated'
                ])->toArray(),
            ];
        }

        // 2. MERGE PENDING CORRECTAMENTE
        // FIX: No usar array_diff porque si una tienda tiene liquidacion cerrada Y nuevos datos pendientes,
        // necesitamos sumar ambos, no ignorar los pendientes.

        $metaIds = array_keys($pendingStoresRaw);
        if (!empty($metaIds)) {
            $meta = Store::query()
                ->select(['id', 'name', 'idpos'])
                ->whereIn('id', $metaIds)
                ->get()
                ->keyBy('id');
        } else {
            $meta = collect();
        }

        foreach ($pendingStoresRaw as $storeId => $raw) {
            $storeId = (int) $storeId;
            $pendingTotal = (float) ($raw['total'] ?? 0);
            $pendingLines = $raw['lines'] ?? [];

            if (isset($finalStoresMap[$storeId])) {
                // Si ya existe (tiene parte liquidada), SUMAR
                $finalStoresMap[$storeId]['total'] += $pendingTotal;
                $finalStoresMap[$storeId]['status'] = 'Parcial'; // Mixed status

                // Merge lines carefully
                $finalStoresMap[$storeId]['lines'] = array_merge(
                    $finalStoresMap[$storeId]['lines'],
                    $pendingLines
                );
            } else {
                // Si es nuevo, Agregar
                $store = $meta->get($storeId);
                $finalStoresMap[$storeId] = [
                    'store_id' => $storeId,
                    'name' => $store?->name ?? 'Tienda',
                    'idpos' => $store?->idpos ?? 'N/D',
                    'total' => $pendingTotal,
                    'status' => 'pending',
                    'lines' => $pendingLines,
                ];
            }
        }

        return collect($finalStoresMap)
            ->filter(fn($row) => $row['total'] > 0)
            ->sortBy(fn($row) => $row['idpos'])
            ->values()
            ->toArray();
    }
}
