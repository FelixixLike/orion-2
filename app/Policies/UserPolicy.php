<?php

namespace App\Policies;

use App\Domain\User\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view any users.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('users.view', 'admin');
    }

    /**
     * Determine if the user can view the user.
     */
    public function view(User $user, User $model): bool
    {
        return $user->hasPermissionTo('users.view', 'admin');
    }

    /**
     * Determine if the user can create users.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('users.create', 'admin');
    }

    // ==================== MÉTODOS AUXILIARES ====================

    /**
     * Verifica si el usuario es super_admin
     */
    private function isSuperAdmin(User $user): bool
    {
        return $user->hasRole('super_admin', 'admin');
    }

    /**
     * Verifica si el usuario es administrator
     */
    private function isAdministrator(User $user): bool
    {
        return $user->hasRole('administrator', 'admin');
    }

    /**
     * Verifica si el usuario está editándose a sí mismo
     */
    private function isEditingSelf(User $user, User $model): bool
    {
        return $user->id === $model->id;
    }

    /**
     * Determine if the user can update the user.
     * 
     * Reglas:
     * - Super Admin puede editarse a sí mismo (con restricciones en campos)
     * - Usuarios con "users.update" pueden editarse a sí mismos (solo info personal y credenciales)
     * - No pueden auto-cambiar: rol, estado, permisos (protegido en Resource/Pages)
     * - El super_admin NO puede ser editado por otros
     * - Solo super_admin puede editar a otros administradores
     */
    public function update(User $user, User $model): bool
    {
        // Editándose a sí mismo
        if ($this->isEditingSelf($user, $model)) {
            return $this->isSuperAdmin($user) || $user->hasPermissionTo('users.update', 'admin');
        }
        
        // Nadie puede editar al super_admin excepto él mismo
        if ($this->isSuperAdmin($model)) {
            return false;
        }
        
        // Solo super_admin puede editar a otros administrators
        if ($this->isAdministrator($model) && !$this->isSuperAdmin($user)) {
            return false;
        }
        
        return $user->hasPermissionTo('users.update', 'admin');
    }


    /**
     * Determine if the user can reset passwords.
     * 
     * @param User|null $model Si se proporciona, verifica si puede resetear la contraseña de ese usuario específico
     */
    public function resetPassword(User $user, ?User $model = null): bool
    {
        if (!$model) {
            return $user->hasPermissionTo('users.reset_password', 'admin');
        }
        
        // Cualquiera puede cambiar su propia contraseña
        if ($this->isEditingSelf($user, $model)) {
            return true;
        }
        
        // Nadie puede resetear la contraseña del super_admin
        if ($this->isSuperAdmin($model)) {
            return false;
        }
        
        return $user->hasPermissionTo('users.reset_password', 'admin');
    }

    /**
     * Determine if the user can update user status.
     * 
     * @param User|null $model Si se proporciona, verifica si puede cambiar el estado de ese usuario específico
     */
    public function updateStatus(User $user, ?User $model = null): bool
    {
        if (!$model) {
            return $user->hasPermissionTo('users.update_status', 'admin');
        }
        
        // Nadie puede cambiar el estado del super_admin
        if ($this->isSuperAdmin($model)) {
            return false;
        }
        
        // Nadie puede cambiar su propio estado
        if ($this->isEditingSelf($user, $model)) {
            return false;
        }
        
        // Solo super_admin puede cambiar estado de otros administrators
        if ($this->isAdministrator($model) && !$this->isSuperAdmin($user)) {
            return false;
        }
        
        return $user->hasPermissionTo('users.update_status', 'admin');
    }

    /**
     * Determine if the user can assign roles.
     * 
     * @param User|null $model Si se proporciona, verifica si puede asignar roles a ese usuario específico
     */
    public function assignRoles(User $user, ?User $model = null): bool
    {
        if (!$model) {
            return $user->hasPermissionTo('users.assign_roles', 'admin');
        }
        
        // El rol del super_admin no puede ser cambiado
        if ($this->isSuperAdmin($model)) {
            return false;
        }
        
        // Nadie puede cambiar su propio rol
        if ($this->isEditingSelf($user, $model)) {
            return false;
        }
        
        // Solo super_admin puede cambiar roles de otros administrators
        if ($this->isAdministrator($model) && !$this->isSuperAdmin($user)) {
            return false;
        }
        
        return $user->hasPermissionTo('users.assign_roles', 'admin');
    }

    /**
     * Determine if the user can assign permissions.
     * 
     * @param User|null $model Si se proporciona, verifica si puede asignar permisos a ese usuario específico
     */
    public function assignPermissions(User $user, ?User $model = null): bool
    {
        if (!$model) {
            return $user->hasPermissionTo('users.assign_permissions', 'admin');
        }
        
        // Los permisos del super_admin no pueden ser cambiados
        if ($this->isSuperAdmin($model)) {
            return false;
        }
        
        // Nadie puede cambiar sus propios permisos
        if ($this->isEditingSelf($user, $model)) {
            return false;
        }
        
        // Solo super_admin puede cambiar permisos de otros administrators
        if ($this->isAdministrator($model) && !$this->isSuperAdmin($user)) {
            return false;
        }
        
        return $user->hasPermissionTo('users.assign_permissions', 'admin');
    }
}
