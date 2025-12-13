<?php

namespace App\Domain\Admin\Filament\Resources;

use App\Domain\Admin\Filament\Resources\UserResource;
use App\Domain\Admin\Filament\Resources\RetailerResource\Pages;
use App\Domain\User\Models\User;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class RetailerResource extends UserResource
{
    protected static ?string $model = User::class;

    /**
     * Usamos un slug explнcito para que las rutas generadas coincidan con
     * `filament.admin.resources.retailers.*` en el panel `admin`.
     */
    protected static ?string $slug = 'retailers';

    protected static bool $shouldRegisterNavigation = false;

    public static function getNavigationLabel(): string
    {
        return 'Tenderos';
    }

    public static function getModelLabel(): string
    {
        return 'Tendero';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Tenderos';
    }

    public static function canViewAny(): bool
    {
        $user = auth('admin')->user();

        return $user?->hasAnyRole(['super_admin', 'administrator', 'tat_direction'], 'admin') ?? false;
    }

    /**
     * Permite que super_admin, administrator y tat_direction creen tenderos
     * sin depender de los permisos genéricos del módulo de usuarios.
     */
    public static function canCreate(): bool
    {
        $user = auth('admin')->user();

        return $user?->hasAnyRole(['super_admin', 'administrator', 'tat_direction'], 'admin') ?? false;
    }

    /**
     * Misma regla de autorización que para crear: los mismos roles pueden editar
     * tenderos existentes.
     */
    public static function canEdit(Model $record): bool
    {
        return static::canCreate();
    }

    public static function getEloquentQuery(): Builder
    {
        // Partimos de la query base (roles + tiendas) y la restringimos a roles guard retailer.
        return parent::getEloquentQuery()
            ->whereHas('roles', function (Builder $query) {
                $query->where('guard_name', 'retailer');
            });
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                \Filament\Tables\Columns\TextColumn::make('first_name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                \Filament\Tables\Columns\TextColumn::make('last_name')
                    ->label('Apellido')
                    ->searchable()
                    ->sortable(),

                \Filament\Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),

                \Filament\Tables\Columns\TextColumn::make('id_number')
                    ->label('Cédula')
                    ->searchable()
                    ->sortable(),

                \Filament\Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(
                        fn(string $state): string =>
                        \App\Domain\User\Enums\UserStatus::tryFrom($state)?->label() ?? $state
                    )
                    ->colors(\App\Domain\User\Enums\UserStatus::badgeColors())
                    ->sortable(),

                \Filament\Tables\Columns\TextColumn::make('tenderer_balance')
                    ->label('Saldo tendero (COP)')
                    ->state(function ($record) {
                        $balanceService = new \App\Domain\Retailer\Support\BalanceService();
                        $storeIds = $record->stores?->pluck('id')->all() ?? [];

                        $total = 0;
                        foreach ($storeIds as $storeId) {
                            $total += $balanceService->getStoreBalance((int) $storeId);
                        }

                        return $total;
                    })
                    ->formatStateUsing(fn($state) => $state === null ? 'N/A' : \Illuminate\Support\Number::currency((float) $state, 'COP'))
                    ->toggleable(isToggledHiddenByDefault: true),

                \Filament\Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

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
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options(\App\Domain\User\Enums\UserStatus::options()),

                \Filament\Tables\Filters\Filter::make('criteria')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('first_name')->label('Nombre'),
                        \Filament\Forms\Components\TextInput::make('last_name')->label('Apellido'),
                        \Filament\Forms\Components\TextInput::make('email')->label('Email'),
                        \Filament\Forms\Components\TextInput::make('id_number')->label('Cédula'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['first_name'], fn(Builder $query, $term) => $query->where('first_name', 'ilike', "%{$term}%"))
                            ->when($data['last_name'], fn(Builder $query, $term) => $query->where('last_name', 'ilike', "%{$term}%"))
                            ->when($data['email'], fn(Builder $query, $term) => $query->where('email', 'ilike', "%{$term}%"))
                            ->when($data['id_number'], fn(Builder $query, $term) => $query->where('id_number', 'ilike', "%{$term}%"));
                    })
            ])
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([25, 50]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRetailers::route('/'),
            'create' => Pages\CreateRetailer::route('/create'),
            'edit' => Pages\EditRetailer::route('/{record}/edit'),
        ];
    }
}
