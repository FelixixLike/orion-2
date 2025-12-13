<?php

namespace App\Domain\Admin\Filament\Resources;

use App\Domain\Admin\Filament\Resources\UserResource\Pages;
use App\Domain\User\Config\UserModulePermissions;
use App\Domain\User\Enums\IdType;
use App\Domain\User\Enums\UserRole;
use App\Domain\User\Enums\UserStatus;
use App\Domain\User\Models\User;
use App\Support\Filament\CheckboxDependencyResolver;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\MultiSelect;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use App\Domain\Store\Models\Store;
use App\Domain\Retailer\Support\BalanceService;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static bool $shouldRegisterNavigation = false;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-users';
    }

    public static function getNavigationLabel(): string
    {
        return 'Usuarios';
    }

    public static function getNavigationSort(): ?int
    {
        return 60;
    }

    public static function getModelLabel(): string
    {
        return 'Usuario';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Usuarios';
    }

    public static function canViewAny(): bool
    {
        $user = auth('admin')->user();
        // Solo el super_admin puede acceder al módulo de Usuarios.
        return $user?->hasRole(UserRole::SUPERADMIN->value, 'admin') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::$shouldRegisterNavigation && static::canViewAny();
    }

    // ========== Helper Methods para Permisos ==========

    protected static function canResetPassword(): bool
    {
        /** @var \App\Domain\User\Models\User $user */
        $user = auth('admin')->user();
        return $user->can('resetPassword', User::class);
    }

    protected static function canUpdateStatus(): bool
    {
        /** @var \App\Domain\User\Models\User $user */
        $user = auth('admin')->user();
        return $user->can('updateStatus', User::class);
    }

    protected static function canAssignRoles(): bool
    {
        /** @var \App\Domain\User\Models\User $user */
        $user = auth('admin')->user();
        return $user->can('assignRoles', User::class);
    }

    protected static function canAssignPermissions(): bool
    {
        /** @var \App\Domain\User\Models\User $user */
        $user = auth('admin')->user();
        return $user->can('assignPermissions', User::class);
    }

    // ========== Password Generation ==========

    protected static function generateSecurePassword(): string
    {
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $numbers = '0123456789';
        $special = '!@#$%^&*';

        // Asegurar al menos uno de cada tipo
        $password =
            $lowercase[random_int(0, strlen($lowercase) - 1)] .
            $uppercase[random_int(0, strlen($uppercase) - 1)] .
            $numbers[random_int(0, strlen($numbers) - 1)] .
            $special[random_int(0, strlen($special) - 1)];

        // Completar hasta 12 caracteres con caracteres aleatorios
        $allChars = $lowercase . $uppercase . $numbers . $special;
        for ($i = 0; $i < 8; $i++) {
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }

        // Mezclar aleatoriamente
        return str_shuffle($password);
    }

    // ===================================================

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['roles', 'stores', 'creator', 'modifier']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información Personal')
                    ->schema([
                        TextInput::make('first_name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('last_name')
                            ->label('Apellido')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        TextInput::make('phone')
                            ->label('Teléfono')
                            ->maxLength(20)
                            ->placeholder('3001234567')
                            ->dehydrateStateUsing(function ($state) {
                                if (!$state)
                                    return null;

                                // Limpiar espacios, guiones y paréntesis
                                $state = preg_replace('/[\s\-()]/', '', $state);

                                // Si no empieza con +, agregar +57 (Colombia)
                                if (!str_starts_with($state, '+')) {
                                    // Remover 0 inicial si existe (números colombianos a veces empiezan con 0)
                                    $state = '+57' . ltrim($state, '0');
                                }

                                return $state;
                            }),

                        Select::make('id_type')
                            ->label('Tipo de Documento')
                            ->options(IdType::options())
                            ->required(),

                        TextInput::make('id_number')
                            ->label('Número de Documento')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50),
                    ])
                    ->columns(2),

                Section::make('Credenciales de Acceso')
                    ->schema([
                        TextInput::make('username')
                            ->label('Usuario')
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->helperText('Nombre de usuario para acceder al sistema')
                            ->required(),

                        TextInput::make('password')
                            ->label('Contraseña')
                            ->password()
                            ->revealable()
                            ->minLength(\App\Domain\Auth\Services\PasswordStrengthCalculator::MIN_LENGTH)
                            ->live(debounce: 300)
                            ->rules([
                                'nullable',
                                'regex:/[a-z]/',      // Al menos una minúscula
                                'regex:/[A-Z]/',      // Al menos una mayúscula
                                'regex:/[0-9]/',      // Al menos un número
                                'regex:/[^a-zA-Z0-9]/', // Al menos un carácter especial
                            ])
                            ->validationMessages([
                                'regex' => 'Debe incluir mayúsculas, minúsculas, números y caracteres especiales.',
                            ])
                            ->dehydrated(fn($state) => filled($state))
                            ->required(fn($context) => $context === 'create')
                            ->helperText(
                                fn($context) => $context === 'create'
                                ? '💡 Debe incluir: 8+ caracteres, mayúsculas, minúsculas, números y símbolos (!@#$%...)'
                                : 'Déjala vacía si no deseas cambiarla'
                            )
                            ->suffixAction(
                                Action::make('generate')
                                    ->icon('heroicon-m-sparkles')
                                    ->label('Generar')
                                    ->action(function (callable $set) {
                                        $password = self::generateSecurePassword();
                                        $set('password', $password);

                                        \Filament\Notifications\Notification::make()
                                            ->title('Contraseña generada')
                                            ->body("Contraseña: **{$password}**")
                                            ->success()
                                            ->duration(15000)
                                            ->send();
                                    })
                                    ->disabled(function (string $context, $record = null): bool {
                                        if ($context === 'create') {
                                            return false;
                                        }

                                        $isEditingSelf = $record && auth('admin')->id() === $record->id;

                                        // Permitir si estás editando tu propio usuario o si eres super_admin/administrator
                                        $currentUser = auth('admin')->user();
                                        $canReset = $currentUser && $currentUser->hasAnyRole(['super_admin', 'administrator'], 'admin');

                                        if ($isEditingSelf || $canReset) {
                                            return false;
                                        }

                                        return true;
                                    })
                            )
                            // Permitir establecer contraseña manualmente para Retailers también
                            ->visible(true),

                        Checkbox::make('must_change_password')
                            ->label('Forzar cambio de contraseña en el primer login')
                            ->default(true)
                            ->helperText('El usuario deberá cambiar esta contraseña temporal al ingresar por primera vez')
                            ->columnSpanFull()
                    ])
                    ->description('Establece una contraseña temporal que deberás comunicar al usuario por un canal seguro')
                    ->columns(2),

                Section::make('Estado del Usuario')
                    ->schema([
                        Select::make('role')
                            ->label('Rol Principal')
                            ->options(function ($record = null): array {
                                $currentUser = auth('admin')->user();

                                if ($currentUser?->hasRole(UserRole::TAT_DIRECTION->value, 'admin')) {
                                    return [
                                        UserRole::RETAILER->value => UserRole::RETAILER->label(),
                                    ];
                                }

                                if (request()->routeIs('filament.admin.resources.retailers.*')) {
                                    return [
                                        UserRole::RETAILER->value => UserRole::RETAILER->label(),
                                    ];
                                }

                                $options = UserRole::options();
                                unset($options[UserRole::RETAILER->value]);

                                if ($record && auth('admin')->id() === $record->id) {
                                    $currentRole = $record->roles()->first();
                                    if ($currentRole && $currentRole->name === 'super_admin') {
                                        return $options;
                                    }
                                }

                                unset($options[UserRole::SUPERADMIN->value]);

                                return $options;
                            })
                            ->required()
                            ->live()
                            ->disabled(function ($record = null): bool {
                                $isEditingSelf = $record && auth('admin')->id() === $record->id;

                                // Solo super_admin puede asignar roles
                                $currentUser = auth('admin')->user();
                                $canAssignRoles = $currentUser && $currentUser->hasRole('super_admin', 'admin');

                                return $isEditingSelf || !$canAssignRoles;
                            })
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    if ($state === UserRole::RETAILER->value) {
                                        $set('permissions', []);
                                        $set('permissions_prev', []);
                                        $set('status', UserStatus::INACTIVE->value);
                                        return;
                                    }

                                    $set('status', UserStatus::ACTIVE->value);

                                    $roleName = $state;
                                    $defaultPermissions = [];

                                    if ($roleName === UserRole::SUPERADMIN->value) {
                                        $defaultPermissions = UserModulePermissions::all();
                                    } elseif ($roleName === UserRole::ADMINISTRATOR->value) {
                                        $all = UserModulePermissions::all();
                                        $defaultPermissions = array_values(array_filter($all, fn($p) => $p !== 'users.view'));
                                    } elseif ($roleName === UserRole::TAT_DIRECTION->value) {
                                        $defaultPermissions = [
                                            'stores.view',
                                            'sales_conditions.view',
                                            'redemptions.view',
                                            'retailers.view',
                                        ];
                                    }

                                    $set('permissions', $defaultPermissions);
                                    $set('permissions_prev', $defaultPermissions);
                                }
                            })
                            ->helperText('El rol define los permisos base del usuario')
                            ->default(fn() => request()->routeIs('filament.admin.resources.retailers.create') ? UserRole::RETAILER->value : null)
                            // Hide Role field if we are in Retailer Resource context
                            ->hidden(fn() => request()->routeIs('filament.admin.resources.retailers.*')),

                        Select::make('status')
                            ->label('Estado')
                            ->options(UserStatus::options())
                            ->default(UserStatus::INACTIVE->value)
                            ->required()
                            ->live()
                            ->disabled(function ($record = null, $get = null): bool {
                                $isEditingSelf = $record && auth('admin')->id() === $record->id;

                                // En creación, si es retailer, siempre deshabilitado (siempre será inactive)
                                if (!$record && $get && $get('role') === UserRole::RETAILER->value) {
                                    return true;
                                }

                                // Solo super_admin y administrator pueden cambiar estado
                                $currentUser = auth('admin')->user();
                                $canUpdateStatus = $currentUser && $currentUser->hasAnyRole(['super_admin', 'administrator'], 'admin');

                                return $isEditingSelf || !$canUpdateStatus;
                            })
                            ->helperText(function ($state, $record = null): string {
                                if ($state === UserStatus::INACTIVE->value || $state === 'inactive') {
                                    return '⚠️ ALERTA: Si el estado es "Inactivo", el usuario NO PODRÁ INGRESAR al sistema hasta que active su cuenta o sea activado manualmente.';
                                }
                                if ($state === 'suspended') {
                                    return '⛔ ALERTA: El usuario está suspendido y NO PODRÁ INGRESAR al sistema.';
                                }

                                return 'El usuario tiene acceso normal al sistema.';
                            })
                            ->hidden(fn() => request()->routeIs('filament.admin.resources.retailers.create'))
                            ->default(fn() => request()->routeIs('filament.admin.resources.retailers.create') ? UserStatus::ACTIVE->value : UserStatus::INACTIVE->value),

                    ])
                    ->columns(2),

                Section::make('Link de Activación')
                    ->schema([
                        TextInput::make('activation_link')
                            ->label('Link de Activación')
                            ->disabled()
                            ->dehydrated(false)
                            ->default(function ($record) {
                                if (!$record)
                                    return null;

                                $role = $record->roles()->first();
                                $isRetailer = $role && $role->guard_name === 'retailer';

                                if (!$isRetailer)
                                    return null;

                                // Solo mostrar link si el usuario está INACTIVE y tiene token válido
                                if (
                                    $record->status === 'inactive' &&
                                    $record->activation_token_plain &&
                                    $record->activation_token &&
                                    $record->activation_token_expires_at &&
                                    $record->activation_token_expires_at->isFuture()
                                ) {
                                    // Usar el token original en texto plano
                                    $activationUrl = url("/portal/activate/{$record->activation_token_plain}");
                                    return $activationUrl;
                                }

                                return null;
                            })
                            ->afterStateHydrated(function ($component, $state, $record) {
                                // Asegurar que se cargue el valor después de hidratar
                                if (!$record)
                                    return;

                                $role = $record->roles()->first();
                                $isRetailer = $role && $role->guard_name === 'retailer';

                                if (!$isRetailer)
                                    return;

                                // Solo mostrar link si el usuario está INACTIVE y tiene token válido
                                if (
                                    $record->status === 'inactive' &&
                                    $record->activation_token_plain &&
                                    $record->activation_token &&
                                    $record->activation_token_expires_at &&
                                    $record->activation_token_expires_at->isFuture()
                                ) {
                                    $activationUrl = url("/portal/activate/{$record->activation_token_plain}");
                                    $component->state($activationUrl);
                                }
                            })
                            ->suffixAction(
                                Action::make('generate')
                                    ->label('Generar/Regenerar Link')
                                    ->icon('heroicon-o-key')
                                    ->color('primary')
                                    ->requiresConfirmation()
                                    ->modalHeading('Generar Link de Activación')
                                    ->modalDescription('¿Estás seguro? Si ya existe un link activo, este será invalidado y se generará uno nuevo.')
                                    ->action(function ($record, callable $set) {
                                        if (!$record)
                                            return;

                                        $role = $record->roles()->first();
                                        $isRetailer = $role && $role->guard_name === 'retailer';

                                        if (!$isRetailer)
                                            return;

                                        // Solo generar token si el usuario está INACTIVE
                                        if ($record->status !== 'inactive') {
                                            $statusLabel = match ($record->status) {
                                                'active' => 'Activo',
                                                'suspended' => 'Suspendido',
                                                default => $record->status,
                                            };

                                            \Filament\Notifications\Notification::make()
                                                ->warning()
                                                ->title('No se puede generar token')
                                                ->body("El usuario está en estado '{$statusLabel}'. Solo se pueden generar tokens de activación para usuarios en estado 'Inactivo'.")
                                                ->persistent()
                                                ->send();
                                            return;
                                        }

                                        // Generar nuevo token
                                        $token = Str::random(64);
                                        $activationUrl = url("/portal/activate/{$token}");

                                        $record->update([
                                            'activation_token' => hash('sha256', $token),
                                            'activation_token_expires_at' => now()->addHours(48),
                                            'activation_token_plain' => $token, // Guardar token en texto plano
                                        ]);

                                        // Actualizar el campo con el nuevo link
                                        $set('activation_link', $activationUrl);

                                        \Filament\Notifications\Notification::make()
                                            ->success()
                                            ->title('Link generado')
                                            ->body('Nuevo link de activación generado. Válido por 48 horas.')
                                            ->persistent()
                                            ->send();
                                    })
                                    ->visible(function ($record) {
                                        if (!$record)
                                            return false;

                                        $role = $record->roles()->first();
                                        $isRetailer = $role && $role->guard_name === 'retailer';

                                        if (!$isRetailer)
                                            return false;

                                        // Solo mostrar si el usuario está INACTIVE
                                        if ($record->status !== 'inactive') {
                                            return false;
                                        }

                                        // Mostrar solo si:
                                        // 1. No hay token pendiente
                                        // 2. El token expiró
                                        // 3. No hay token en texto plano
                                        $hasValidToken = $record->activation_token_plain &&
                                            $record->activation_token &&
                                            $record->activation_token_expires_at &&
                                            $record->activation_token_expires_at->isFuture();

                                        // Mostrar si NO hay token válido
                                        return !$hasValidToken;
                                    })
                            )
                            ->suffixAction(
                                Action::make('copy')
                                    ->label('Copiar')
                                    ->icon('heroicon-o-clipboard')
                                    ->color('success')
                                    ->action(function ($record, $component) {
                                        $link = $component->getState();
                                        if ($link) {
                                            \Filament\Notifications\Notification::make()
                                                ->success()
                                                ->title('Link copiado')
                                                ->body('El link de activación ha sido copiado al portapapeles.')
                                                ->send();

                                            // Copiar al portapapeles usando JavaScript
                                            return "navigator.clipboard.writeText('{$link}')";
                                        } else {
                                            \Filament\Notifications\Notification::make()
                                                ->warning()
                                                ->title('No hay link')
                                                ->body('Primero debes generar un link de activación.')
                                                ->send();
                                        }
                                    })
                                    ->visible(function ($component) {
                                        return !empty($component->getState());
                                    })
                            )
                            ->helperText(function ($record) {
                                if (!$record)
                                    return '';

                                $role = $record->roles()->first();
                                $isRetailer = $role && $role->guard_name === 'retailer';

                                if (!$isRetailer)
                                    return '';

                                // Mensaje según el estado del usuario
                                if ($record->status === 'active') {
                                    return '⚠️ El usuario está activo. No se puede generar link de activación.';
                                }

                                if ($record->status === 'suspended') {
                                    return '⚠️ El usuario está suspendido. No se puede generar link de activación hasta que se reactive.';
                                }

                                // Si está inactive, mostrar información del token
                                $hasToken = $record->activation_token &&
                                    $record->activation_token_expires_at &&
                                    $record->activation_token_expires_at->isFuture();

                                if ($hasToken) {
                                    $expiresAt = $record->activation_token_expires_at->diffForHumans();
                                    return "Este link permite al usuario activar su cuenta. Válido por 48 horas. Expira: {$expiresAt}. Haz clic en 'Generar Link' para crear uno nuevo.";
                                }

                                return 'Este link permite al usuario activar su cuenta. Haz clic en "Generar Link" para crear uno nuevo. Válido por 48 horas.';
                            })
                            ->visible(function ($record) {
                                if (!$record) {
                                    return false;
                                }

                                $role = $record->roles()->first();

                                // Solo aplica a tenderos inactivos: cuando el usuario ya está activo
                                // el link de activación deja de ser relevante y ocultamos la sección.
                                if (!($role && $role->guard_name === 'retailer')) {
                                    return false;
                                }

                                return $record->status === 'inactive';
                            }),
                    ])
                    ->visible(function ($record) {
                        if (!$record) {
                            return false;
                        }

                        $role = $record->roles()->first();

                        return $role && $role->guard_name === 'retailer' && $record->status === 'inactive';
                    })
                    ->collapsible()
                    ->collapsed(false),

                Section::make('Tiendas asociadas')
                    ->schema([
                        MultiSelect::make('stores')
                            ->label('Tiendas asignadas')
                            ->relationship('stores')
                            ->getOptionLabelFromRecordUsing(fn(Store $record) => "{$record->idpos} - {$record->name}")
                            ->afterStateHydrated(function ($component, $state, $record) {
                                // Al editar un tendero, mostramos como seleccionadas tanto
                                // las tiendas vinculadas por pivot (stores) como aquellas
                                // donde es tendero principal (ownedStores), para que el
                                // administrador vea las mismas tiendas que en el portal.
                                if (!$record) {
                                    return;
                                }

                                /** @var \App\Domain\User\Models\User $record */
                                $pivotIds = $record->stores()->pluck('stores.id')->all();
                                $ownedIds = $record->ownedStores()->pluck('id')->all();

                                $component->state(array_values(array_unique([
                                    ...$pivotIds,
                                    ...$ownedIds,
                                ])));
                            })
                            ->getSearchResultsUsing(function (string $search, ?\Illuminate\Database\Eloquent\Model $record) {
                                return Store::query()
                                    ->where(function ($query) use ($search) {
                                        $query->where('name', 'ilike', "%{$search}%")
                                            ->orWhere('idpos', 'ilike', "%{$search}%");
                                    })
                                    // FILTRO: Solo mostrar tiendas LIBRES o asignadas a ESTE usuario
                                    ->whereDoesntHave('users', function ($q) use ($record) {
                                        if ($record) {
                                            $q->where('users.id', '!=', $record->id);
                                        }
                                        // Si no hay record (creación), whereDoesntHave('users') filtra cualquier tienda con dueño
                                    })
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn(Store $store) => [
                                        $store->id => "{$store->idpos} - {$store->name}"
                                    ]);
                            })
                            ->getOptionLabelUsing(
                                fn($value): ?string =>
                                Store::find($value)?->idpos . ' - ' . (Store::find($value)?->name)
                            )
                            ->placeholder('Selecciona tiendas para este tendero')
                            ->visible(fn($get) => $get('role') === UserRole::RETAILER->value)
                            ->hidden(function () {
                                $user = auth('admin')->user();
                                return !$user || !$user->hasRole(['super_admin', 'administrator', 'tat_direction'], 'admin');
                            })
                            ->required(false)
                            ->rules([
                                function ($record) {
                                    return function (string $attribute, $value, \Closure $fail) use ($record) {
                                        if (empty($value) || !is_array($value)) {
                                            return;
                                        }

                                        // Verificar si alguna de las tiendas seleccionadas ya está asignada a otro usuario (en tabla pivot)
                                        $conflicts = \Illuminate\Support\Facades\DB::table('store_user')
                                            ->join('users', 'store_user.user_id', '=', 'users.id')
                                            ->join('stores', 'store_user.store_id', '=', 'stores.id')
                                            ->whereIn('store_user.store_id', $value)
                                            ->where('store_user.user_id', '!=', $record?->id ?? 0)
                                            ->select('stores.idpos', 'stores.name', 'users.first_name', 'users.last_name')
                                            ->get();

                                        if ($conflicts->isNotEmpty()) {
                                            $details = $conflicts->map(fn($item) => "{$item->idpos} - {$item->name} (usuario: {$item->first_name} {$item->last_name})")->join(', ');
                                            $fail("Las siguientes tiendas ya están asignadas a otro tendero: {$details}. Debe desvincularlas primero.");
                                        }
                                    };
                                },
                            ]),
                    ])
                    ->visible(fn($get): bool => $get('role') === UserRole::RETAILER->value)
                    ->columns(1)
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

                TextColumn::make('first_name')
                    ->label('Nombre')
                    ->placeholder('Juan')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('last_name')
                    ->label('Apellido')
                    ->placeholder('Perez')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->placeholder('ejemplo@correo.com')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('id_number')
                    ->label('Cédula')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('roles.name')
                    ->label('Rol')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        $roles = $record->roles->pluck('name')->toArray();

                        // Si tiene super_admin, no mostrar administrator
                        if (in_array('super_admin', $roles)) {
                            $roles = array_filter($roles, fn($role) => $role !== 'administrator');
                        }

                        return $roles;
                    })
                    ->formatStateUsing(
                        fn(?string $state): string =>
                        $state ? (UserRole::tryFrom($state)?->label() ?? $state) : 'Sin rol'
                    )
                    ->colors(UserRole::badgeColors()),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(
                        fn(string $state): string =>
                        UserStatus::tryFrom($state)?->label() ?? $state
                    )
                    ->colors(UserStatus::badgeColors())
                    ->sortable(),

                TextColumn::make('tenderer_balance')
                    ->label('Saldo tendero (COP)')
                    ->state(function ($record) {
                        $roles = $record->roles->pluck('name')->toArray();
                        if (!in_array(UserRole::RETAILER->value, $roles, true)) {
                            return null;
                        }

                        $balanceService = new BalanceService();
                        $storeIds = $record->stores?->pluck('id')->all() ?? [];

                        $total = 0;
                        foreach ($storeIds as $storeId) {
                            $total += $balanceService->getStoreBalance((int) $storeId);
                        }

                        return $total;
                    })
                    ->formatStateUsing(fn($state) => $state === null ? 'N/A' : Number::currency((float) $state, 'COP'))
                    ->toggleable(isToggledHiddenByDefault: true),

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
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(UserStatus::options()),

                SelectFilter::make('roles')
                    ->label('Rol')
                    ->relationship('roles', 'name')
                    ->options(UserRole::options()), // Mostrar todos los roles en filtro (incluye super_admin para verlo)
            ])
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([25, 50]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
