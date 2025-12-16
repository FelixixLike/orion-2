<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Admin\Filament\Resources\SimcardResource\RelationManagers;

use App\Domain\Admin\Filament\Resources\SimcardResource;
use App\Domain\Import\Models\OperatorReport;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\DB;

class OperatorReportsRelationManager extends RelationManager
{
    protected static string $relationship = 'operatorReports';

    protected static ?string $title = 'Reportes del Operador';

    protected function getTableQuery(): ?\Illuminate\Database\Eloquent\Builder
    {
        // Create a subquery to get distinct periods with calculated totals
        $expression = 'CASE WHEN COALESCE(payment_percentage, 0) > 1 THEN COALESCE(payment_percentage, 0) / 100 ELSE COALESCE(payment_percentage, 0) END';

        $subQuery = DB::table('operator_reports')
            ->selectRaw("
                MIN(id) as id, 
                recharge_period as periodo,
                SUM(commission_paid_80 + commission_paid_20) as total_commission,
                SUM(recharge_amount * ({$expression})) as valor_a_pagar
            ")
            ->where('simcard_id', $this->getOwnerRecord()->id)
            ->groupBy('recharge_period');
        
        // Create base query from subquery - this returns a Query\Builder
        // CRITICAL: Alias must be 'operator_reports' because Filament will try to 
        // order by 'operator_reports.id'. By aliasing the subquery, we make that valid.
        $baseQuery = DB::table('temp')
            ->fromSub($subQuery, 'operator_reports');
        
        // Create a new Eloquent builder and set its base query directly
        $eloquentBuilder = \App\Domain\Import\Models\OperatorReport::query();
        $eloquentBuilder->setQuery($baseQuery);
        
        return $eloquentBuilder;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                // We don't need complex order manipulation anymore
                // since we aliased the table correctly
            })
            ->columns([
                TextColumn::make('periodo')
                    ->label('Periodo')
                    ->searchable(),

                TextColumn::make('total_commission')
                    ->label('Total Comisión')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' COP')
                    ->sortable(),

                TextColumn::make('valor_a_pagar')
                    ->label('Valor a Pagar')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' COP')
                    ->sortable(),

                TextColumn::make('diferencia')
                    ->label('Diferencia')
                    ->state(fn ($record): float => $record->total_commission - $record->valor_a_pagar)
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' COP')
                    ->sortable(false)
                    ->color(fn ($state): string => $state < 0 ? 'danger' : 'success'),
            ])
            ->searchable(false)
            ->paginated(false)
            ->recordUrl(
                fn ($record): string => SimcardResource::getUrl(
                    'view-period-reports',
                    [
                        'record' => $this->getOwnerRecord()->id,
                        'period' => $record->periodo
                    ]
                )
            );
    }
}
