<?php

namespace App\Domain\Admin\Filament\Resources;

use App\Domain\Admin\Filament\Resources\StoreResource\Pages;
use App\Domain\Retailer\Support\BalanceService;
use App\Domain\Route\Models\Route as SalesRoute;
use App\Domain\Store\Enums\Municipality;
use App\Domain\Store\Enums\StoreCategory;
use App\Domain\Store\Enums\StoreStatus;
use App\Domain\Store\Models\Store;
use App\Domain\User\Models\User;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;

class StoreResource extends Resource
{
    protected static ?string $model = Store::class;

    protected static bool $shouldRegisterNavigation = false;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-building-storefront';
    }

    public static function getNavigationLabel(): string
    {
        return 'Tiendas';
    }

    public static function getNavigationSort(): ?int
    {
        return 40;
    }

    public static function getModelLabel(): string
    {
        return 'Tienda';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Tiendas';
    }

    public static function canViewAny(): bool
    {
        $user = Auth::guard('admin')->user();
        return $user?->hasRole(['super_admin', 'administrator', 'tat_direction', 'management', 'supervisor'], 'admin')
            || $user?->hasPermissionTo('stores.view', 'admin')
            ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::$shouldRegisterNavigation && static::canViewAny();
    }

    // Crear/editar: super_admin, administrator, tat_direction. Borrar: solo super_admin/administrator
    public static function canCreate(): bool
    {
        $user = Auth::guard('admin')->user();
        return $user?->hasRole(['super_admin', 'administrator', 'tat_direction'], 'admin') ?? false;
    }

    public static function canEdit($record): bool
    {
        $user = Auth::guard('admin')->user();
        return $user?->hasRole(['super_admin', 'administrator', 'tat_direction'], 'admin') ?? false;
    }

    public static function canDelete($record): bool
    {
        $user = Auth::guard('admin')->user();
        return $user?->hasRole(['super_admin', 'administrator'], 'admin') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Informacion General')
                ->schema([
                    TextInput::make('idpos')
                        ->label('ID_PDV')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(20)
                        ->minLength(1)
                        ->helperText('Código único del punto de venta'),

                    TextInput::make('name')
                        ->label('Nombre del punto')
                        ->required()
                        ->maxLength(255),

                    Select::make('status')
                        ->label('Estado')
                        ->options(StoreStatus::options())
                        ->default(StoreStatus::ACTIVE->value)
                        ->required(),

                    Select::make('category')
                        ->label('Categoría')
                        ->options(StoreCategory::options())
                        ->nullable(),
                ])
                ->columns(2),

            Section::make('Asignacion y Contacto')
                ->schema([
                    Select::make('user_id')
                        ->label('Tendero')
                        ->options(fn() => User::query()
                            ->whereHas('roles', fn($q) => $q->where('name', 'retailer')->where('guard_name', 'retailer'))
                            ->select('id', 'first_name', 'last_name', 'id_number')
                            ->get()
                            ->mapWithKeys(fn($u) => [$u->id => trim($u->getFilamentName() . ' ' . ($u->id_number ?? ''))])
                            ->toArray())
                        ->searchable()
                        ->placeholder('Selecciona tendero (retailer)')
                        ->helperText('Tendero principal dueño del PDV'),

                    TextInput::make('phone')
                        ->label('Celular')
                        ->nullable()
                        ->tel()
                        ->maxLength(20),

                    TextInput::make('email')
                        ->label('Correo electrónico')
                        ->nullable()
                        ->email()
                        ->maxLength(255),
                ])
                ->columns(2),

            Section::make('Ubicacion')
                ->schema([
                    Select::make('route_code')
                        ->label('Ruta')
                        ->nullable()
                        ->searchable()
                        ->preload()
                        ->options(fn() => SalesRoute::query()
                            ->whereNotNull('code')
                            ->where(function ($q) {
                                $q->whereRaw('LOWER(type) = ?', ['route'])
                                    ->orWhereNull('type');
                            })
                            ->orderBy('code')
                            ->pluck('code', 'code')
                            ->toArray())
                        ->getSearchResultsUsing(function (string $search): array {
                            return SalesRoute::query()
                                ->whereNotNull('code')
                                ->where(function ($q) {
                                    $q->whereRaw('LOWER(type) = ?', ['route'])
                                        ->orWhereNull('type');
                                })
                                ->where('code', 'ILIKE', '%' . $search . '%')
                                ->orderBy('code')
                                ->limit(50)
                                ->pluck('code', 'code')
                                ->toArray();
                        })
                        ->createOptionForm([
                            TextInput::make('code')
                                ->label('Código de ruta')
                                ->required()
                                ->rules(['required', 'max:50', 'unique:routes,code'])
                                ->validationMessages([
                                    'unique' => 'Ya existe una ruta con este código.',
                                ])
                                ->maxLength(50),
                            TextInput::make('name')
                                ->label('Nombre descriptivo')
                                ->maxLength(255)
                                ->nullable(),
                            Placeholder::make('existing_routes')
                                ->label('Rutas registradas')
                                ->content(function () {
                                    $codes = SalesRoute::query()
                                        ->whereNotNull('code')
                                        ->where(function ($q) {
                                            $q->whereRaw('LOWER(type) = ?', ['route'])
                                                ->orWhereNull('type');
                                        })
                                        ->orderBy('code')
                                        ->limit(50)
                                        ->pluck('code')
                                        ->toArray();

                                    $html = view('filament.admin.stores.partials.list-codes', [
                                        'title' => empty($codes) ? 'Aún no hay rutas creadas.' : 'Rutas ya creadas (máx. 50):',
                                        'codes' => $codes,
                                    ])->render();

                                    return new HtmlString($html);
                                })
                                ->columnSpanFull(),
                        ])
                        ->createOptionUsing(function (array $data): string {
                            $code = strtoupper(trim($data['code'] ?? ''));
                            $name = $data['name'] ?? null;

                            if ($code === '') {
                                throw new \InvalidArgumentException('El código de ruta es obligatorio.');
                            }

                            // Evita duplicados: si existe, devuelve el código existente.
                            if ($existing = SalesRoute::query()->whereRaw('UPPER(code) = ?', [$code])->first()) {
                                return $existing->code;
                            }

                            $route = SalesRoute::create([
                                'code' => $code,
                                'name' => $name,
                                'type' => 'route',
                                'active' => true,
                            ]);

                            return $route->code;
                        })
                        ->placeholder('Selecciona o crea una ruta')
                        ->helperText('Selecciona una ruta existente o escribe un código nuevo para crearla.'),

                    Select::make('circuit_code')
                        ->label('Circuito')
                        ->nullable()
                        ->searchable()
                        ->preload()
                        ->options(fn() => SalesRoute::query()
                            ->whereNotNull('code')
                            ->where(function ($q) {
                                $q->whereRaw('LOWER(type) = ?', ['circuit'])
                                    ->orWhereNull('type');
                            })
                            ->orderBy('code')
                            ->pluck('code', 'code')
                            ->toArray())
                        ->getSearchResultsUsing(function (string $search): array {
                            return SalesRoute::query()
                                ->whereNotNull('code')
                                ->where(function ($q) {
                                    $q->whereRaw('LOWER(type) = ?', ['circuit'])
                                        ->orWhereNull('type');
                                })
                                ->where('code', 'ILIKE', '%' . $search . '%')
                                ->orderBy('code')
                                ->limit(50)
                                ->pluck('code', 'code')
                                ->toArray();
                        })
                        ->createOptionForm([
                            TextInput::make('code')
                                ->label('Código de circuito')
                                ->required()
                                ->rules(['required', 'max:50', 'unique:routes,code'])
                                ->validationMessages([
                                    'unique' => 'Ya existe un circuito con este código.',
                                ])
                                ->maxLength(50),
                            TextInput::make('name')
                                ->label('Nombre descriptivo')
                                ->maxLength(255)
                                ->nullable(),
                            Placeholder::make('existing_circuits')
                                ->label('Circuitos registrados')
                                ->content(function () {
                                    $codes = SalesRoute::query()
                                        ->whereNotNull('code')
                                        ->where(function ($q) {
                                            $q->whereRaw('LOWER(type) = ?', ['circuit'])
                                                ->orWhereNull('type');
                                        })
                                        ->orderBy('code')
                                        ->limit(50)
                                        ->pluck('code')
                                        ->toArray();

                                    $html = view('filament.admin.stores.partials.list-codes', [
                                        'title' => empty($codes) ? 'Aún no hay circuitos creados.' : 'Circuitos ya creados (máx. 50):',
                                        'codes' => $codes,
                                    ])->render();

                                    return new HtmlString($html);
                                })
                                ->columnSpanFull(),
                        ])
                        ->createOptionUsing(function (array $data): string {
                            $code = strtoupper(trim($data['code'] ?? ''));
                            $name = $data['name'] ?? null;

                            if ($code === '') {
                                throw new \InvalidArgumentException('El código de circuito es obligatorio.');
                            }

                            // Evita duplicados: si existe, devuelve el código existente.
                            if ($existing = SalesRoute::query()->whereRaw('UPPER(code) = ?', [$code])->first()) {
                                return $existing->code;
                            }

                            $route = SalesRoute::create([
                                'code' => $code,
                                'name' => $name,
                                'type' => 'circuit',
                                'active' => true,
                            ]);

                            return $route->code;
                        })
                        ->placeholder('Selecciona o crea un circuito')
                        ->helperText('Selecciona un circuito existente o escribe un código nuevo para crearla.'),

                    Select::make('municipality')
                        ->label('Municipio')
                        ->options(Municipality::metaOptions())
                        ->nullable(),

                    TextInput::make('neighborhood')
                        ->label('Barrio')
                        ->nullable()
                        ->maxLength(255),

                    TextInput::make('address')
                        ->label('Direccion')
                        ->nullable()
                        ->maxLength(255),
                ])
                ->columns(2),

        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with([
                'user:id,first_name,last_name,id_number,phone,email',
                'users:id,username,first_name,last_name,id_number',
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

            return $query->where(function (Builder $scoped) use ($routes) {
                $scoped->whereIn('route_code', $routes)
                    ->orWhereIn('circuit_code', $routes);
            });
        }

        return $query->whereRaw('1=0');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('idpos')
                    ->label('ID_PDV')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Tienda')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('route_code')
                    ->label('Ruta')
                    ->toggleable()
                    ->sortable(),

                TextColumn::make('circuit_code')
                    ->label('Circuito')
                    ->toggleable()
                    ->sortable(),

                TextColumn::make('tenderer.name')
                    ->label('Tendero')
                    ->state(function (Store $store) {
                        $tenderer = $store->tenderer ?? $store->users->first();
                        if (!$tenderer) {
                            return null;
                        }

                        $name = $tenderer->getFilamentName();
                        $idNumber = $tenderer->id_number;

                        return $idNumber ? "{$name} (CC {$idNumber})" : $name;
                    })
                    ->placeholder('Sin asignar')
                    ->toggleable(),

                TextColumn::make('category')
                    ->label('Categoría')
                    ->badge()
                    ->formatStateUsing(fn($state) => $state instanceof StoreCategory ? $state->label() : (StoreCategory::tryFrom($state)?->label() ?? $state))
                    ->colors(StoreCategory::badgeColors())
                    ->sortable(),

                TextColumn::make('municipality')
                    ->label('Municipio')
                    ->formatStateUsing(fn($state) => $state instanceof Municipality ? $state->label() : (Municipality::tryFrom($state)?->label() ?? $state))
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn($state) => $state instanceof StoreStatus ? $state->label() : (StoreStatus::tryFrom($state)?->label() ?? $state))
                    ->colors(StoreStatus::badgeColors())
                    ->sortable(),

                TextColumn::make('balance')
                    ->label('Saldo (COP)')
                    ->state(function (Store $store) {
                        $balanceService = new BalanceService();
                        return $balanceService->getStoreBalance($store->id);
                    })
                    ->formatStateUsing(fn($state) => Number::currency((float) ($state ?? 0), 'COP'))
                    ->tooltip('Ingresos (liquidaciones cerradas) menos salidas (redenciones aprobadas/entregadas/confirmadas)')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('creator.name')
                    ->label('Creado por')
                    ->formatStateUsing(fn($state, $record) => $record->creator ? "{$record->creator->name} (CC {$record->creator->id_number})" : null)
                    ->description(fn($record) => $record->created_at?->timezone('America/Bogota')->format('d/m/Y h:i A'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('modifier.name')
                    ->label('Actualizado por')
                    ->formatStateUsing(fn($state, $record) => $record->modifier ? "{$record->modifier->name} (CC {$record->modifier->id_number})" : null)
                    ->description(fn($record) => $record->updated_at?->timezone('America/Bogota')->format('d/m/Y h:i A'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(StoreStatus::options()),

                SelectFilter::make('category')
                    ->label('Categoría')
                    ->options(StoreCategory::options()),

                SelectFilter::make('municipality')
                    ->label('Municipio')
                    ->options(Municipality::options()),

                SelectFilter::make('route_code')
                    ->label('Ruta')
                    ->options(fn() => Store::query()->whereNotNull('route_code')->distinct()->pluck('route_code', 'route_code')->toArray()),

                SelectFilter::make('circuit_code')
                    ->label('Circuito')
                    ->options(fn() => Store::query()->whereNotNull('circuit_code')->distinct()->pluck('circuit_code', 'circuit_code')->toArray()),

                SelectFilter::make('user_id')
                    ->label('Tendero')
                    ->options(fn() => User::query()->whereHas('roles', fn($q) => $q->where('name', 'retailer')->where('guard_name', 'retailer'))->pluck('first_name', 'id')->toArray()),
            ])
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([25, 50]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStores::route('/'),
            'create' => Pages\CreateStore::route('/create'),
            'edit' => Pages\EditStore::route('/{record}/edit'),
        ];
    }
}
