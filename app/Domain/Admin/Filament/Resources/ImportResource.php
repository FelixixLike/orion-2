<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
/**
 * @author Andrés Felipe Martínez González <felixix-like@outlook.es>
 * @copyright 2025 Derechos Reservados. Uso exclusivo bajo licencia privada.
 */

namespace App\Domain\Admin\Filament\Resources;

use App\Domain\Admin\Filament\Resources\ImportResource\Forms\ImportForm;
use App\Domain\Admin\Filament\Resources\ImportResource\Pages;
use App\Domain\Admin\Filament\Resources\ImportResource\Tables\ImportsTable;
use App\Domain\Import\Models\Import;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ImportResource extends Resource
{
    protected static ?string $model = Import::class;

    protected static bool $shouldRegisterNavigation = false;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-arrow-up-tray';
    }

    public static function getNavigationLabel(): string
    {
        return 'Importación';
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }

    public static function getModelLabel(): string
    {
        return 'Importación';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Importaciones';
    }

    /**
     * Visible solo para super_admin / administrator o quien tenga permiso imports.view
     */
    public static function canViewAny(): bool
    {
        $user = auth('admin')->user();

        if (!$user) {
            return false;
        }

        if ($user->hasRole(['super_admin', 'administrator', 'management'], 'admin')) {
            return true;
        }

        return $user->hasPermissionTo('imports.view', 'admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        // El menú lateral debe apuntar al módulo (ImportModulePage),
        // no al listado directo de importaciones.
        return false;
    }

    public static function canCreate(): bool
    {
        $user = auth('admin')->user();
        return $user?->hasRole(['super_admin', 'administrator', 'tat_direction'], 'admin') ?? false;
    }

    public static function canEdit($record): bool
    {
        $user = auth('admin')->user();
        return $user?->hasRole(['super_admin', 'administrator'], 'admin') ?? false;
    }

    public static function canDelete($record): bool
    {
        $user = auth('admin')->user();
        return $user?->hasRole(['super_admin', 'administrator'], 'admin') ?? false;
    }

    public static function canView($record): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return ImportForm::configure($schema);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['creator', 'modifier']);
    }

    public static function table(Table $table): Table
    {
        return ImportsTable::configure($table)
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('type')
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

                \Filament\Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'pending' => 'warning',
                        'processing' => 'info',
                        'completed' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    // ... (status column)
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'pending' => 'Pendiente',
                        'processing' => 'Procesando',
                        'completed' => 'Completado',
                        'failed' => 'Fallido',
                        default => $state,
                    })
                    ->sortable(),

                \Filament\Tables\Columns\TextColumn::make('progress')
                    ->label('Progreso')
                    ->state(function (Import $record): string {
                        if ($record->status === 'pending') {
                            return '-';
                        }
                        return number_format($record->processed_rows ?? 0) . ' / ' . number_format($record->total_rows ?? 0);
                    })
                    ->badge()
                    ->color(fn(Import $record): string => match ($record->status) {
                        'processing' => 'info',
                        'completed' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),


                \Filament\Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                \Filament\Tables\Columns\TextColumn::make('creator.name')
                    ->label('Creado por')
                    ->formatStateUsing(fn($state, $record) => $record->creator ? "{$record->creator->name} - {$record->creator->id_number}" : null)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                \Filament\Tables\Columns\TextColumn::make('modifier.name')
                    ->label('Actualizado por')
                    ->formatStateUsing(fn($state, $record) => $record->modifier ? "{$record->modifier->name} - {$record->modifier->id_number}" : null)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                \Filament\Tables\Columns\TextColumn::make('description')
                    ->label('Descripción')
                    ->placeholder('Sin descripción')
                    ->limit(50)
                    ->wrap()
                    ->searchable(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListImports::route('/'),
            'create' => Pages\CreateImport::route('/create'),
            'view' => Pages\ViewImport::route('/{record}'),
        ];
    }
}
