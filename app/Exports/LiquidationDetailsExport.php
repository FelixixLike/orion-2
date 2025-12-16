<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromIterator;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldQueue;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

class LiquidationDetailsExport implements FromIterator, WithHeadings, ShouldQueue
{
    use Exportable;

    public function __construct(
        protected int $userId,
        protected string $period,
        protected ?string $search = null
    ) {
    }

    public function iterator(): \Iterator
    {
        ini_set('memory_limit', '2048M');
        ini_set('max_execution_time', 1800); // 30 mins

        $key = 'preview_data_' . $this->userId . '_' . $this->period;
        $data = Cache::get($key);

        if (!$data) {
            return;
        }

        $allStores = collect($data);

        if ($this->search) {
            $search = strtolower($this->search);
            $allStores = $allStores->filter(
                fn($s) =>
                str_contains(strtolower($s['name']), $search) ||
                str_contains((string) $s['idpos'], $search)
            );
        }

        foreach ($allStores as $store) {
            if (empty($store['lines']))
                continue;
            foreach ($store['lines'] as $line) {
                yield [
                    'Código Tienda' => $store['idpos'],
                    'Nombre Tienda' => $store['name'],
                    'ICCID' => $line['iccid'] ?? 'N/D',
                    'Teléfono' => $line['phone_number'] ?? 'N/D',
                    '% Comisión' => number_format($line['residual_percentage'], 2) . '%',
                    'Recarga Movilco' => $line['movilco_recharge_amount'],
                    'Base Liquidación' => $line['base_liquidation_final'],
                    'Total a Pagar' => $line['pago_residual'],
                ];
            }
        }
    }

    public function headings(): array
    {
        return ['CÓDIGO TIENDA', 'NOMBRE TIENDA', 'ICCID', 'TELÉFONO', '% COMISIÓN', 'RECARGA MOVILCO', 'BASE LIQUIDACIÓN', 'TOTAL A PAGAR'];
    }
}
