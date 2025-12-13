<?php

namespace App\Domain\Admin\Filament\Resources\SimcardResource\Pages;

use App\Domain\Admin\Filament\Resources\SimcardResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Support\Facades\DB;

class ViewSimcard extends ViewRecord
{
    protected static string $resource = SimcardResource::class;

    protected string $view = 'filament.admin.crosses.pages.view-simcard-custom';

    public function getGlobalSummaryData(): array
    {
        $expression = 'CASE WHEN COALESCE(payment_percentage, 0) > 1 THEN COALESCE(payment_percentage, 0) / 100 ELSE COALESCE(payment_percentage, 0) END';
        $totals = DB::table('operator_reports')
            ->where('simcard_id', $this->record->id)
            ->selectRaw("
                SUM(commission_paid_80 + commission_paid_20) as total_commission,
                SUM(recharge_amount * ({$expression})) as total_valor_a_pagar
            ")
            ->first();

        $diferencia = ($totals->total_commission ?? 0) - ($totals->total_valor_a_pagar ?? 0);

        return [
            'totalComission' => number_format($totals->total_commission ?? 0, 0, ',', '.'),
            'totalValorAPagar' => number_format($totals->total_valor_a_pagar ?? 0, 0, ',', '.'),
            'diferencia' => number_format($diferencia, 0, ',', '.'),
            'diferenciaColor' => $diferencia < 0 ? 'danger' : 'success',
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        $summary = $this->getGlobalSummaryData();

        return $schema->components([
            Section::make('Detalle de SIM')
                ->schema([
                    TextEntry::make('id')->label('ID'),
                    TextEntry::make('iccid')->label('ICCID'),
                    TextEntry::make('phone_number')->label('Telefono'),
                    TextEntry::make('created_at')->label('Creado')->dateTime('d/m/Y H:i'),
                ])
                ->columns(2)
                ->columnSpan(1),

            Section::make('Resumen')
                ->schema([
                    TextEntry::make('total_comision')
                        ->label('Total Comisión')
                        ->state($summary['totalComission'] . ' COP'),
                    TextEntry::make('diferencia_total')
                        ->label('Diferencia Total')
                        ->state($summary['diferencia'] . ' COP')
                        ->color($summary['diferenciaColor']),
                    TextEntry::make('total_valor_pagar')
                        ->label('Total Valor a Pagar')
                        ->state($summary['totalValorAPagar'] . ' COP'),
                ])
                ->columns(2)
                ->columnSpan(1),
        ])
        ->columns(2);
    }
}
