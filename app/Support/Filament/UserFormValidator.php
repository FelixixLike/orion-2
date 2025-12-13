<?php

namespace App\Support\Filament;

use App\Domain\User\Config\UserModulePermissions;
use App\Domain\User\Enums\UserRole;
use App\Domain\User\Models\User;
use Filament\Notifications\Notification;

/**
 * Utilidades de validación para formularios de usuarios en Filament.
 */
class UserFormValidator
{
    public static function validatePassword(
        array $data,
        User $currentUser,
        ?User $targetUser,
        array &$criticalChanges = []
    ): array {
        if (empty($data['password'])) {
            unset($data['password']);

            return $data;
        }

        // Creación: permitir y mapear directamente.
        if (!$targetUser) {
            $data['password_hash'] = $data['password'];
            unset($data['password']);

            return $data;
        }

        // Edición: validar permiso.
        if (!$currentUser->can('resetPassword', $targetUser)) {
            unset($data['password']);
            self::sendWarningNotification(
                'Cambio de contraseña no permitido',
                'No tienes permiso para resetear contraseñas.'
            );

            return $data;
        }

        $data['password_hash'] = $data['password'];
        $criticalChanges['password_changed'] = true;
        unset($data['password']);

        return $data;
    }

    public static function validateStatus(
        array $data,
        User $currentUser,
        User $targetUser,
        array &$criticalChanges = []
    ): array {
        if (!isset($data['status']) || $data['status'] === $targetUser->status) {
            return $data;
        }

        if (!$currentUser->can('updateStatus', $targetUser)) {
            $data['status'] = $targetUser->status;
            self::sendWarningNotification(
                'Cambio de estado no permitido',
                'No tienes permiso para cambiar el estado de usuarios.'
            );

            return $data;
        }

        $criticalChanges['status_changed'] = $data['status'];

        return $data;
    }

    public static function validateRole(
        array $data,
        User $currentUser,
        ?User $targetUser,
        array &$criticalChanges = []
    ): ?string {
        if (!isset($data['role'])) {
            return null;
        }

        $requestedRole = $data['role'];

        // Permitir que tat_direction asigne tendero aunque no tenga permiso genérico.
        $isTatDirection = $currentUser->hasRole('tat_direction', 'admin');
        $isRetailerRole = $requestedRole === UserRole::RETAILER->value;

        if (!($isTatDirection && $isRetailerRole)) {
            if (!$currentUser->can('assignRoles', $targetUser ?? User::class)) {
                self::sendWarningNotification(
                    'Asignación de rol no permitida',
                    'No tienes permiso para asignar roles.'
                );

                return null;
            }
        }

        // Detectar cambio de rol en edición.
        if ($targetUser) {
            $role = $targetUser->roles()->first();
            $currentRole = $role?->name;

            if ($requestedRole !== $currentRole) {
                $criticalChanges['role_changed'] = $requestedRole;
            }
        }

        return $requestedRole;
    }

    public static function validatePermissions(
        array $data,
        User $currentUser,
        ?User $targetUser,
        array &$criticalChanges = []
    ): array {
        if (!isset($data['permissions'])) {
            return [];
        }

        $targetRole = $data['role'] ?? $targetUser?->roles()->first()?->name;



        if (!$currentUser->can('assignPermissions', $targetUser ?? User::class)) {
            self::sendWarningNotification(
                'Asignación de permisos no permitida',
                'No tienes permiso para asignar permisos directos.'
            );

            return [];
        }

        // Resolver dependencias de permisos.
        $permissions = \App\Support\Auth\PermissionResolver::resolve($data['permissions']);

        // Detectar cambio de permisos en edición.
        if ($targetUser) {
            $currentPermissions = $targetUser->getAllPermissions()->pluck('name')->toArray();
            sort($currentPermissions);
            $newPermissions = $permissions;
            sort($newPermissions);

            if ($currentPermissions !== $newPermissions) {
                $criticalChanges['permissions_changed'] = true;
            }
        }

        return $permissions;
    }

    private static function sendWarningNotification(string $title, string $body): void
    {
        Notification::make()
            ->warning()
            ->title($title)
            ->body($body)
            ->send();
    }
}
