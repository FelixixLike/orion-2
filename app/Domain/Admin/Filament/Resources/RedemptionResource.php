<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Admin\Filament\Resources;

use App\Domain\Admin\Filament\Resources\RedemptionResource\Pages;
use App\Domain\Store\Models\Redemption;
use App\Domain\Store\Models\Store;
use App\Domain\User\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class RedemptionResource extends Resource
{
    protected static ?string $model = Redemption::class;

    protected static bool $shouldRegisterNavigation = true;

    public const STATUSES = [
        'pending' => 'Pendiente',
        'approved' => 'Aprobada',
        'delivered' => 'Entregada',
        'rejected' => 'Rechazada',
        'cancelled' => 'Cancelada',
    ];



    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-ticket';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Redenciones';
    }

    public static function getNavigationLabel(): string
    {
        return 'Redenciones';
    }

    public static function getNavigationSort(): ?int
    {
        return 130;
    }

    public static function getModelLabel(): string
    {
        return 'Redencion';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Redenciones';
    }

    /**
     * Visibilidad según permisos.
     */
    public static function canViewAny(): bool
    {
        $user = Auth::guard('admin')->user();
        return $user?->hasPermissionTo('redemptions.view', 'admin') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canDelete($record): bool
    {
        $user = Auth::guard('admin')->user();
        return $user?->hasRole(['super_admin', 'administrator'], 'admin') ?? false;
    }

    public static function canCreate(): bool
    {
        $user = Auth::guard('admin')->user();
        return $user?->hasRole(['super_admin', 'administrator'], 'admin') ?? false;
    }

    public static function canEdit($record): bool
    {
        $user = Auth::guard('admin')->user();
        return $user?->hasPermissionTo('redemptions.edit', 'admin') ?? false;
    }

    protected static function isTat(): bool
    {
        $user = Auth::guard('admin')->user();
        return $user?->hasRole('tat_direction', 'admin') ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with([
                'store:id,name,idpos,municipality,user_id',
                'store.tenderer:id,first_name,last_name',
                'redemptionProduct:id,name',
                'handledByUser:id,first_name,last_name',
                'creator:id,first_name,last_name,id_number,email',
                'modifier:id,first_name,last_name,id_number,email',
            ]);

        $user = Auth::guard('admin')->user();
        if (!$user) {
            return $query->whereRaw('1=0');
        }

        if ($user->hasRole(['super_admin', 'administrator', 'management', 'tat_direction'], 'admin')) {
            return $query;
        }

        if ($user->hasRole('supervisor', 'admin')) {
            $routes = $user->supervisorRouteList();
            if (empty($routes)) {
                return $query->whereRaw('1=0');
            }

            return $query->whereHas('store', function (Builder $storeQuery) use ($routes) {
                $storeQuery->whereIn('route_code', $routes)
                    ->orWhereIn('circuit_code', $routes);
            });
        }

        return $query->whereRaw('1=0');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Datos de la redencion')
                ->schema([
                    Select::make('store_id')
                        ->label('Tienda')
                        ->relationship('store', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->disabled(fn() => self::isTat()),
                    Select::make('liquidation_id')
                        ->label('Liquidacion')
                        ->relationship('liquidation', 'id')
                        ->nullable()
                        ->preload()
                        ->searchable()
                        ->disabled(fn() => self::isTat()),
                    Select::make('redemption_product_id')
                        ->label('Producto')
                        ->relationship('redemptionProduct', 'name')
                        ->preload()
                        ->searchable()
                        ->required()
                        ->disabled(fn() => self::isTat()),
                    TextInput::make('quantity')
                        ->label('Cantidad')
                        ->numeric()
                        ->required()
                        ->disabled(fn() => self::isTat()),
                    TextInput::make('total_value')
                        ->label('Valor total')
                        ->numeric()
                        ->prefix('$')
                        ->required()
                        ->disabled(fn() => self::isTat()),
                    DateTimePicker::make('requested_at')
                        ->label('Fecha solicitud')
                        ->seconds(false)
                        ->required()
                        ->disabled(fn() => self::isTat()),
                    Select::make('status')
                        ->label('Estado')
                        ->options(function ($record) {
                            $statuses = self::STATUSES;

                            // If creating or no record, return all
                            if (!$record) {
                                return $statuses;
                            }

                            $current = $record->status;

                            // Workflow Logic
                            if ($current === 'pending') {
                                return [
                                    'pending' => 'Pendiente',
                                    'approved' => 'Aprobada',
                                    'rejected' => 'Rechazada',
                                ];
                            }

                            if ($current === 'approved') {
                                return [
                                    'approved' => 'Aprobada',
                                    'delivered' => 'Entregada',
                                ];
                            }

                            // Terminal states or fallback
                            return [
                                $current => $statuses[$current] ?? $current
                            ];
                        })
                        ->required(),
                    Select::make('handled_by_user_id')
                        ->label('Gestionada por')
                        ->relationship(
                            name: 'handledByUser',
                            titleAttribute: 'first_name',
                            modifyQueryUsing: fn(Builder $query) => $query->select('id', 'first_name', 'last_name')
                        )
                        ->getOptionLabelFromRecordUsing(fn(User $user) => $user->getFilamentName())
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->disabled(fn() => self::isTat()),
                    Textarea::make('notes')
                        ->label('Notas')
                        ->rows(3),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('requested_at')
                    ->label('Solicitada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('store.tenderer.id_number')
                    ->label('Cédula')
                    ->placeholder('Sin asignar')
                    ->toggleable()
                    ->sortable(),
                TextColumn::make('store.name')
                    ->label('Tienda')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('store.idpos')
                    ->label('ID_PDV')
                    ->toggleable()
                    ->sortable(),
                TextColumn::make('redemptionProduct.name')
                    ->label('Producto')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('quantity')
                    ->label('Cantidad')
                    ->sortable(),
                TextColumn::make('total_value')
                    ->label('Valor total')
                    ->money('COP', true)
                    ->sortable(),
                BadgeColumn::make('status')
                    ->label('Estado')
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'approved',
                        'success' => 'delivered',
                        'danger' => ['rejected', 'cancelled'],
                    ])
                    ->formatStateUsing(fn(string $state) => self::STATUSES[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('handledByUser.name')
                    ->label('Gestionada por')
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
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(self::STATUSES),
                SelectFilter::make('store_id')
                    ->label('Tienda')
                    ->options(fn() => Store::query()->orderBy('name')->pluck('name', 'id')->toArray()),
                SelectFilter::make('idpos')
                    ->label('IDPOS')
                    ->query(function (Builder $query, $value) {
                        if (!$value) {
                            return $query;
                        }

                        return $query->whereHas('store', function (Builder $storeQuery) use ($value) {
                            $storeQuery->where('idpos', $value);
                        });
                    })
                    ->options(fn() => Store::query()
                        ->whereNotNull('idpos')
                        ->orderBy('idpos')
                        ->pluck('idpos', 'idpos')
                        ->toArray()),
                SelectFilter::make('tenderer_id')
                    ->label('Cédula tendero')
                    ->query(function (Builder $query, $value) {
                        if (!$value) {
                            return $query;
                        }

                        return $query->whereHas('store.tenderer', function (Builder $tendererQuery) use ($value) {
                            $tendererQuery->where('id_number', $value);
                        });
                    })
                    ->options(fn() => User::query()
                        ->whereHas('roles', fn($q) => $q->where('name', 'retailer')->where('guard_name', 'retailer'))
                        ->whereNotNull('id_number')
                        ->orderBy('id_number')
                        ->pluck('id_number', 'id_number')
                        ->toArray()),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn() => !self::isTat())
                    ->mutateFormDataUsing(fn(array $data) => $data),
                EditAction::make('tat-status-only')
                    ->label('Actualizar estado')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->visible(fn() => self::isTat())
                    ->mutateFormDataUsing(fn(array $data) => ['status' => $data['status'] ?? null]),
            ])
            ->bulkActions([
                DeleteBulkAction::make()
                    ->visible(fn() => !self::isTat()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRedemptions::route('/'),
            'create' => Pages\CreateRedemption::route('/create'),
            'edit' => Pages\EditRedemption::route('/{record}/edit'),
            'view' => Pages\ViewRedemption::route('/{record}'),
        ];
    }
}
