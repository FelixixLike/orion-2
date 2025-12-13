<?php

namespace App\Domain\Admin\Filament\Resources\UserResource\Pages;

use App\Domain\Admin\Filament\Resources\UserResource;
use App\Support\Auth\SessionInvalidator;
use App\Support\Filament\UserFormValidator;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Str;
use App\Domain\User\Enums\UserRole;
use Spatie\Permission\Models\Role;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    private ?string $roleName = null;
    private array $permissions = [];
    private array $criticalChanges = [];

    protected function getHeaderActions(): array
    {
        return [
            // No hay acciones en el header (los usuarios solo se activan/desactivan, no se eliminan)
        ];
    }

    /**
     * Obtiene las acciones adicionales del formulario.
     */
    protected function getFormActions(): array
    {
        return [
            ...parent::getFormActions(),
            $this->getRegenerateActivationTokenAction(),
        ];
    }

    /**
     * Acción para regenerar el token de activación.
     */
    protected function getRegenerateActivationTokenAction(): ?Action
    {
        /** @var \App\Domain\User\Models\User $user */
        $user = $this->record;

        $role = $user->roles()->first();
        /** @var \Spatie\Permission\Models\Role|null $role */
        $isRetailer = $role !== null && $role->guard_name === 'retailer';

        if (!$isRetailer) {
            return null;
        }

        // Solo mostrar si el usuario está INACTIVE y NO hay token válido pendiente
        if ($user->status !== 'inactive') {
            return null; // No mostrar si no está inactive
        }

        /** @var \Illuminate\Support\Carbon|null $expiresAt */
        $expiresAt = $user->activation_token_expires_at;
        $hasValidToken = ($user->activation_token_plain !== null && $user->activation_token_plain !== '') &&
            ($user->activation_token !== null && $user->activation_token !== '') &&
            $expiresAt !== null &&
            $expiresAt->isFuture();

        if ($hasValidToken) {
            return null; // No mostrar si hay token válido
        }

        return Action::make('regenerateActivationToken')
            ->label('Generar Link de Activación')
            ->icon('heroicon-o-key')
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading('Generar Link de Activación')
            ->modalDescription('Se generará un nuevo link de activación para este usuario. Válido por 48 horas.')
            ->action(function () {
                /** @var \App\Domain\User\Models\User $user */
                $user = $this->record;

                // Verificar estado antes de generar
                if ($user->status !== 'inactive') {
                    $statusLabel = match ($user->status) {
                        'active' => 'Activo',
                        'suspended' => 'Suspendido',
                        default => $user->status,
                    };

                    Notification::make()
                        ->warning()
                        ->title('No se puede generar token')
                        ->body("El usuario está en estado '{$statusLabel}'. Solo se pueden generar tokens para usuarios en estado 'Inactivo'.")
                        ->persistent()
                        ->send();
                    return;
                }

                $token = Str::random(64);
                $activationUrl = url("/portal/activate/{$token}");

                $user->update([
                    'activation_token' => hash('sha256', $token),
                    'activation_token_expires_at' => now()->addHours(48),
                    'activation_token_plain' => $token, // Guardar token en texto plano
                ]);

                Notification::make()
                    ->success()
                    ->title('Link generado')
                    ->body("Nuevo link de activación generado. Válido por 48 horas.")
                    ->persistent()
                    ->send();

                // Recargar el formulario para mostrar el nuevo link
                $this->fillForm();
            })
            ->visible(function (): bool {
                /** @var \App\Domain\User\Models\User $user */
                $user = $this->record;

                $role = $user->roles()->first();
                /** @var \Spatie\Permission\Models\Role|null $role */
                $isRetailer = $role !== null && $role->guard_name === 'retailer';

                if (!$isRetailer || $user->status !== 'inactive') {
                    return false;
                }

                /** @var \Illuminate\Support\Carbon|null $expiresAt */
                $expiresAt = $user->activation_token_expires_at;
                $hasValidToken = ($user->activation_token_plain !== null && $user->activation_token_plain !== '') &&
                    ($user->activation_token !== null && $user->activation_token !== '') &&
                    $expiresAt !== null &&
                    $expiresAt->isFuture();

                return !$hasValidToken;
            });
    }

    /**
     * Hook ejecutado antes de cargar el formulario.
     * Protege al super_admin de ser editado por OTROS usuarios.
     */
    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        /** @var \App\Domain\User\Models\User|null $currentUser */
        $currentUser = auth('admin')->user();
        /** @var \App\Domain\User\Models\User $recordUser */
        $recordUser = $this->record;

        // Bloquear que OTROS usuarios editen al super_admin
        // Pero SÍ permitir que el super_admin se edite a sí mismo
        if ($currentUser && $recordUser->hasRole('super_admin', 'admin') && $currentUser->id !== $recordUser->id) {
            Notification::make()
                ->danger()
                ->title('Acceso denegado')
                ->body('🔒 El super_admin no puede ser editado por otros usuarios.')
                ->persistent()
                ->send();

            $this->redirect(UserResource::getUrl('index'));
        }

        // Ahora SÍ permitimos que usuarios con "users.update" se editen a sí mismos
        // pero con restricciones en los campos (manejadas en el formulario)
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var \App\Domain\User\Models\User $user */
        $user = $this->record;

        // Cargar rol principal
        $role = $user->roles()->first();
        /** @var \Spatie\Permission\Models\Role|null $role */
        if ($role) {
            $data['role'] = $role->name;
        }

        // Cargar todos los permisos directos del usuario
        $data['permissions'] = $user->getDirectPermissions()
            ->pluck('name')
            ->values()
            ->toArray();

        // Si es retailer, está INACTIVE y tiene token pendiente, cargar el link de activación
        /** @var \Illuminate\Support\Carbon|null $expiresAt */
        $expiresAt = $user->activation_token_expires_at;
        if (
            $role && $role->guard_name === 'retailer' &&
            $user->status === 'inactive' &&
            $user->activation_token_plain &&
            $user->activation_token &&
            $expiresAt &&
            $expiresAt->isFuture()
        ) {
            // Usar el token original en texto plano para mostrar el link
            $data['activation_link'] = url("/portal/activate/{$user->activation_token_plain}");
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var \App\Domain\User\Models\User|null $currentUser */
        $currentUser = auth('admin')->user();
        /** @var \App\Domain\User\Models\User $record */
        $record = $this->record;

        // Validar y procesar contraseña
        $data = UserFormValidator::validatePassword(
            $data,
            $currentUser,
            $record,
            $this->criticalChanges
        );

        // Validar y procesar estado
        $data = UserFormValidator::validateStatus(
            $data,
            $currentUser,
            $record,
            $this->criticalChanges
        );

        // Si es retailer y cambió el estado de "inactive" a otro, limpiar tokens de activación
        $isRetailer = $record->hasRole('retailer', 'retailer');
        $wasInactive = $record->status === 'inactive';
        $isChangingStatus = isset($data['status']) && $data['status'] !== $record->status;

        if ($isRetailer && $wasInactive && $isChangingStatus && $data['status'] !== 'inactive') {
            // Cambió de inactive a active o suspended - limpiar tokens de activación
            $data['activation_token'] = null;
            $data['activation_token_expires_at'] = null;
            $data['activation_token_plain'] = null;

            $statusLabel = match ($data['status']) {
                'active' => 'Activo',
                'suspended' => 'Suspendido',
                default => $data['status'],
            };

            Notification::make()
                ->info()
                ->title('Tokens de activación invalidados')
                ->body("El usuario cambió a estado '{$statusLabel}'. Los tokens de activación pendientes han sido invalidados.")
                ->persistent()
                ->send();
        }

        // Validar y procesar rol
        $this->roleName = UserFormValidator::validateRole(
            $data,
            $currentUser,
            $record,
            $this->criticalChanges
        );

        // Validar y procesar permisos
        $this->permissions = UserFormValidator::validatePermissions(
            $data,
            $currentUser,
            $record,
            $this->criticalChanges
        );

        // Eliminar campos de formulario que no son de la tabla users
        unset($data['role'], $data['permissions']);

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var \App\Domain\User\Models\User $user */
        $user = $this->record;

        // Sincronizar rol
        if ($this->roleName) {
            $guard = $this->roleName === UserRole::RETAILER->value ? 'retailer' : 'admin';
            $role = Role::findByName($this->roleName, $guard);
            $user->syncRoles([$role]);
        }

        // Sincronizar permisos
        $user->syncPermissions($this->permissions);

        // Invalidar sesiones si hubo cambios críticos
        $this->handleSessionInvalidation();
    }

    /**
     * Invalida sesiones del usuario si hubo cambios críticos de seguridad.
     */
    private function handleSessionInvalidation(): void
    {
        if (!SessionInvalidator::shouldInvalidateSessions($this->criticalChanges)) {
            return;
        }

        /** @var \App\Domain\User\Models\User $record */
        $record = $this->record;

        $reason = SessionInvalidator::getInvalidationReason($this->criticalChanges);
        $deletedSessions = SessionInvalidator::invalidateUserSessions(
            userId: $record->id,
            guard: 'admin',
            reason: $reason
        );

        if ($deletedSessions > 0) {
            Notification::make()
                ->info()
                ->title('Sesiones invalidadas')
                ->body("Se cerraron {$deletedSessions} sesión(es) activa(s) del usuario por seguridad: {$reason}.")
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        $scope = request()->get('scope');

        if ($scope) {
            return $this->getResource()::getUrl('index', ['scope' => $scope]);
        }

        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Usuario actualizado')
            ->body('Los cambios han sido guardados exitosamente.')
            ->duration(5000);
    }
}
