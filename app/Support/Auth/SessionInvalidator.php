<?php

namespace App\Support\Auth;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SessionInvalidator
{
    /**
     * Invalida todas las sesiones activas de un usuario específico.
     *
     * @param int $userId ID del usuario
     * @param string $guard Guardia de autenticación (admin, retailer, etc.)
     * @param string|null $reason Razón de la invalidación (para logs)
     * @return int Número de sesiones eliminadas
     */
    public static function invalidateUserSessions(
        int $userId,
        string $guard = 'admin',
        ?string $reason = null
    ): int {
        $deletedCount = DB::table('sessions')
            ->where('user_id', $userId)
            ->delete();

        if ($deletedCount > 0) {
            Log::info('Sesiones invalidadas', [
                'user_id' => $userId,
                'guard' => $guard,
                'sessions_deleted' => $deletedCount,
                'reason' => $reason ?? 'No especificada',
            ]);
        }

        return $deletedCount;
    }

    /**
     * Determina si los cambios realizados requieren invalidar sesiones.
     *
     * @param array $changes Cambios detectados (ej: ['password' => true, 'status' => 'active'])
     * @return bool
     */
    public static function shouldInvalidateSessions(array $changes): bool
    {
        $criticalFields = [
            'password_changed',
            'status_changed',
            'role_changed',
            'permissions_changed',
        ];

        foreach ($criticalFields as $field) {
            if (!empty($changes[$field])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Obtiene una razón legible para la invalidación basada en los cambios.
     *
     * @param array $changes Cambios detectados
     * @return string
     */
    public static function getInvalidationReason(array $changes): string
    {
        $reasons = [];

        if (!empty($changes['password_changed'])) {
            $reasons[] = 'contraseña actualizada';
        }

        if (!empty($changes['status_changed'])) {
            $reasons[] = "estado cambiado a '{$changes['status_changed']}'";
        }

        if (!empty($changes['role_changed'])) {
            $reasons[] = "rol cambiado a '{$changes['role_changed']}'";
        }

        if (!empty($changes['permissions_changed'])) {
            $reasons[] = 'permisos modificados';
        }

        return implode(', ', $reasons) ?: 'cambios de seguridad';
    }
}

