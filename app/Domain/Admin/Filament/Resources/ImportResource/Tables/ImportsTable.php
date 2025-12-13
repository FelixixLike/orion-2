<?php

declare(strict_types=1);

namespace App\Domain\Admin\Filament\Resources\ImportResource\Tables;

use App\Domain\Import\Models\Import;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ImportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('batch_id')
                    ->label('Tanda')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn(?string $state): string => $state ? substr($state, 0, 8) . '...' : 'Individual')
                    ->searchable()
                    ->toggleable()
                    ->tooltip(fn($record) => $record->batch_id ? 'Click para ver todos los archivos de esta tanda' : null),

                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'operator_report' => 'info',
                        'recharge' => 'success',
                        'sales_condition' => 'warning',
                        'store' => 'info',
                        'redemption_product' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'operator_report' => 'Reporte del Operador',
                        'recharge' => 'Recargas Variables',
                        'sales_condition' => 'Condiciones SIM',
                        'store' => 'Tiendas',
                        'redemption_product' => 'Productos redimibles',
                        default => $state,
                    })
                    ->sortable()
                    ->searchable(),

                TextColumn::make('description')
                    ->label('Descripción')
                    ->placeholder('Sin descripción')
                    ->limit(50)
                    ->wrap()
                    ->searchable(),

                // ... (rest of the table columns)

            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'pending' => 'Pendiente',
                        'processing' => 'Procesando',
                        'completed' => 'Completado',
                        'failed' => 'Fallido',
                    ]),
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'operator_report' => 'Reporte del Operador',
                        'recharge' => 'Recargas Variables',
                        'sales_condition' => 'Condiciones SIM',
                        'store' => 'Tiendas',
                        'redemption_product' => 'Productos redimibles',
                    ]),
                SelectFilter::make('batch_id')
                    ->label('Tanda')
                    ->options(function () {
                        return Import::whereNotNull('batch_id')
                            ->distinct()
                            ->pluck('batch_id', 'batch_id')
                            ->mapWithKeys(fn($batchId) => [
                                $batchId => Str::limit($batchId, 8, '...'),
                            ])
                            ->toArray();
                    })
            ])
            ->defaultSort('id', 'desc')
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([25, 50]);
    }
}
