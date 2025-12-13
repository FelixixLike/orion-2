<?php

namespace App\Domain\Admin\Filament\Resources\SimcardResource\Pages;

use App\Domain\Admin\Filament\Resources\SimcardResource;
use App\Domain\Import\Models\Simcard;
use App\Domain\Import\Models\OperatorReport;
use Filament\Resources\Pages\Page;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;

class ViewPeriodReports extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = SimcardResource::class;

    protected string $view = 'filament.admin.crosses.pages.view-period-reports';

    public Simcard $record;
    public int $period;

    public function mount(int|string|Simcard $record, int $period): void
    {
        // Handle if Filament passes the Simcard object directly
        if ($record instanceof Simcard) {
            $this->record = $record;
        } else {
            // Otherwise it's an ID, fetch the record
            $this->record = Simcard::findOrFail($record);
        }
        
        $this->period = $period;
    }

    public function getTitle(): string|Htmlable
    {
        return "Reportes del Operador - Periodo {$this->period}";
    }

    public function getBreadcrumbs(): array
    {
        return [
            SimcardResource::getUrl('index') => 'Cruces',
            SimcardResource::getUrl('view', ['record' => $this->record]) => "Ver",
            '#' => "Periodo {$this->period}",
        ];
    }

    public function getSummaryData(): array
    {
        $expression = 'CASE WHEN COALESCE(payment_percentage, 0) > 1 THEN COALESCE(payment_percentage, 0) / 100 ELSE COALESCE(payment_percentage, 0) END';

        $totals = DB::table('operator_reports')
            ->where('simcard_id', $this->record->id)
            ->where('recharge_period', $this->period)
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

    protected function getTableQuery(): ?\Illuminate\Database\Eloquent\Builder
    {
        return OperatorReport::query()
            ->where('simcard_id', $this->record->id)
            ->where('recharge_period', $this->period);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('commission_status')
                    ->label('Estado Comisión')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'PAGADA' => 'success',
                        'PENDIENTE' => 'warning',
                        default => 'gray',
                    })
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('activation_date')
                    ->label('Fecha Activación')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('cutoff_date')
                    ->label('Fecha Corte')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('commission_paid_80')
                    ->label('Comisión 80%')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' COP')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('commission_paid_20')
                    ->label('Comisión 20%')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' COP')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('recharge_amount')
                    ->label('Monto Recarga')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' COP')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('total_commission')
                    ->label('Total Comisión')
                    ->state(fn (OperatorReport $record): float => $record->commission_paid_80 + $record->commission_paid_20)
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' COP')
                    ->toggleable(),

                TextColumn::make('valor_a_pagar')
                    ->label('Valor a Pagar')
                    ->state(fn (OperatorReport $record): float => (float) ($record->recharge_amount ?? 0) * (float) ($record->payment_percentage ?? 0))
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' COP')
                    ->toggleable(),

                TextColumn::make('diferencia')
                    ->label('Diferencia')
                    ->state(fn (OperatorReport $record): float => ($record->commission_paid_80 + $record->commission_paid_20) - ((float) ($record->recharge_amount ?? 0) * (float) ($record->payment_percentage ?? 0)))
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' COP')
                    ->color(fn ($state): string => $state < 0 ? 'danger' : 'success')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated(false)
            ->searchable(false)
            ->recordUrl(
                fn ($record): string => \App\Domain\Admin\Filament\Resources\OperatorReportResource::getUrl('edit', ['record' => $record, 'simcard' => $record->simcard_id])
            );
    }
}
