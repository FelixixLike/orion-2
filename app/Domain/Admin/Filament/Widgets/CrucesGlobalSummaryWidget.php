<?php

namespace App\Domain\Admin\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

class CrucesGlobalSummaryWidget extends Widget
{
    protected int | string | array $columnSpan = 'full';

    public function getSummaryData(): array
    {
        $percentageExpression = 'CASE WHEN COALESCE(payment_percentage, 0) > 1 THEN COALESCE(payment_percentage, 0) / 100 ELSE COALESCE(payment_percentage, 0) END';

        $totals = DB::table('operator_reports')
            ->selectRaw("
                SUM(commission_paid_80 + commission_paid_20) as total_commission,
                SUM(recharge_amount * ({$percentageExpression})) as total_valor_a_pagar
            ")
            ->first();

        $diferencia = ($totals->total_commission ?? 0) - ($totals->total_valor_a_pagar ?? 0);

        return [
            'totalComission' => number_format($totals->total_commission ?? 0, 0, ',', '.'),
            'totalValorAPagar' => number_format($totals->total_valor_a_pagar ?? 0, 0, ',', '.'),
            'diferencia' => number_format($diferencia, 0, ',', '.'),
        ];
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('filament.admin.crosses.widgets.cruces-global-summary', [
            'summary' => $this->getSummaryData()
        ]);
    }
}
