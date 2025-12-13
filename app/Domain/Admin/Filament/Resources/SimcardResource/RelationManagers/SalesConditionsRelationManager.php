<?php

namespace App\Domain\Admin\Filament\Resources\SimcardResource\RelationManagers;

use App\Domain\Import\Models\SalesCondition;
use App\Domain\Admin\Exports\SalesConditionsExport;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Notifications\Notification;

class SalesConditionsRelationManager extends RelationManager
{
    protected static string $relationship = 'salesConditions';

    protected static ?string $title = 'Condiciones de Venta';

    protected static ?string $recordTitleAttribute = 'id';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('iccid')
                    ->label('ICCID')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('phone_number')
                    ->label('TELEFONO')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('idpos')
                    ->label('IDPOS')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('sale_price')
                    ->label('VALOR')
                    ->money('COP')
                    ->sortable(),

                TextColumn::make('commission_percentage')
                    ->label('RESIDUAL')
                    ->suffix('%')
                    ->sortable(),

                TextColumn::make('population')
                    ->label('POBLACION')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('period_date')
                    ->label('FECHA VENTA')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('creator.name')
                    ->label('Creado por')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            ->recordUrl(
                fn(SalesCondition $record): string => \App\Domain\Admin\Filament\Resources\SalesConditionResource::getUrl('edit', ['record' => $record, 'simcard' => $record->simcard_id])
            )
            ->headerActions([
                \Filament\Actions\Action::make('export')
                    ->label('Exportar a Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(fn() => $this->exportToExcel()),
            ]);
    }

    public function exportToExcel()
    {
        // Get all records from the current table query (respects filters and search)
        $records = $this->getFilteredTableQuery()->get();

        // Create filename with timestamp
        $filename = 'condiciones_sim_' . now()->format('Y-m-d_His') . '.xlsx';

        // Show notification
        Notification::make()
            ->success()
            ->title('Exportación exitosa')
            ->body("Se exportaron {$records->count()} registros a Excel")
            ->send();

        // Return the download response
        return Excel::download(
            new SalesConditionsExport($records),
            $filename
        );
    }
}
