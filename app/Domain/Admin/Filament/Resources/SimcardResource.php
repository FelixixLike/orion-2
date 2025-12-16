<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Admin\Filament\Resources;

use App\Domain\Admin\Filament\Resources\SimcardResource\Pages;
use App\Domain\Import\Models\Simcard;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SimcardResource extends Resource
{
    protected static ?string $model = Simcard::class;

    protected static ?string $slug = 'matches';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-device-phone-mobile';
    }

    public static function getNavigationLabel(): string
    {
        return 'Cruces';
    }

    public static function getModelLabel(): string
    {
        return 'Cruce';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Cruces';
    }

    public static function canViewAny(): bool
    {
        $user = auth('admin')->user();
        return $user?->hasRole('super_admin', 'admin') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canView($record): bool
    {
        return static::canViewAny();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['creator', 'modifier']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                // Read-only resource, no form needed
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('iccid')
                    ->label('ICCID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->tooltip('Click para copiar'),

                TextColumn::make('phone_number')
                    ->label('Número de Teléfono')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->placeholder('Sin asignar'),

                TextColumn::make('operator_reports_count')
                    ->label('Reportes')
                    ->counts('operatorReports')
                    ->sortable(),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->state(function ($record) {
                        $percentageExpression = 'CASE WHEN COALESCE(payment_percentage, 0) > 1 THEN COALESCE(payment_percentage, 0) / 100 ELSE COALESCE(payment_percentage, 0) END';
                        $totals = \Illuminate\Support\Facades\DB::table('operator_reports')
                            ->where('simcard_id', $record->id)
                            ->selectRaw("
                                SUM(commission_paid_80 + commission_paid_20) as total_commission,
                                SUM(recharge_amount * ({$percentageExpression})) as total_valor_a_pagar
                            ")
                            ->first();

                        $diferencia = ($totals->total_commission ?? 0) - ($totals->total_valor_a_pagar ?? 0);

                        return abs($diferencia) < 0.01 ? 'ok' : 'warning';
                    })
                    ->formatStateUsing(fn() => '')
                    ->icon(fn(string $state): string => $state === 'ok' ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle')
                    ->size('lg')
                    ->iconColor(fn(string $state): string => $state === 'ok' ? 'success' : 'warning')
                    ->alignCenter(),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('creator.name')
                    ->label('Creado por')
                    ->formatStateUsing(fn($state, $record) => $record->creator ? "{$record->creator->name} - {$record->creator->id_number}" : null)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('modifier.name')
                    ->label('Actualizado por')
                    ->formatStateUsing(fn($state, $record) => $record->modifier ? "{$record->modifier->name} - {$record->modifier->id_number}" : null)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->filters([
                Filter::make('with_recharges')
                    ->label('Con Recargas')
                    ->query(fn(Builder $query): Builder => $query->has('recharges')),

                Filter::make('without_recharges')
                    ->label('Sin Recargas')
                    ->query(fn(Builder $query): Builder => $query->doesntHave('recharges')),

                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options([
                        'ok' => 'Sin Diferencias',
                        'warning' => 'Con Diferencias',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (!isset($data['value'])) {
                            return $query;
                        }

                        $estado = $data['value'];

                        return $query->whereHas('operatorReports', function ($q) use ($estado) {
                            $subQuery = DB::table('operator_reports as or_sub')
                                ->selectRaw('
                                    simcard_id,
                                    ABS(SUM(commission_paid_80 + commission_paid_20) - SUM(recharge_amount * (
                                        CASE WHEN COALESCE(payment_percentage, 0) > 1
                                            THEN COALESCE(payment_percentage, 0) / 100
                                            ELSE COALESCE(payment_percentage, 0)
                                        END
                                    ))) as diferencia
                                ')
                                ->groupBy('simcard_id');

                            if ($estado === 'ok') {
                                $q->whereIn('simcard_id', function ($query) use ($subQuery) {
                                    $query->select('simcard_id')
                                        ->fromSub($subQuery, 'totals')
                                        ->where('diferencia', '<', 0.01);
                                });
                            } else {
                                $q->whereIn('simcard_id', function ($query) use ($subQuery) {
                                    $query->select('simcard_id')
                                        ->fromSub($subQuery, 'totals')
                                        ->where('diferencia', '>=', 0.01);
                                });
                            }
                        });
                    }),

                Filter::make('fecha_corte')
                    ->label('Fecha de Corte')
                    ->form([
                        DatePicker::make('desde')
                            ->label('Desde'),
                        DatePicker::make('hasta')
                            ->label('Hasta')
                            ->default(now()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['desde'],
                                fn(Builder $query, $date): Builder => $query->whereHas('operatorReports', function ($q) use ($date) {
                                    $q->where('cutoff_date', '>=', $date);
                                }),
                            )
                            ->when(
                                $data['hasta'],
                                fn(Builder $query, $date): Builder => $query->whereHas('operatorReports', function ($q) use ($date) {
                                    $q->where('cutoff_date', '<=', $date);
                                }),
                            );
                    }),
            ])
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([25, 50])
            ->defaultSort('id', 'desc')
            ->recordUrl(
                fn(Simcard $record): string => Pages\ViewSimcard::getUrl([$record->id]),
            );
    }

    public static function getRelations(): array
    {
        return [
            SimcardResource\RelationManagers\OperatorReportsRelationManager::class,
            SimcardResource\RelationManagers\SalesConditionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSimcards::route('/'),
            'view' => Pages\ViewSimcard::route('/{record}'),
            'view-period-reports' => Pages\ViewPeriodReports::route('/{record}/period/{period}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // Read-only resource
    }
}
