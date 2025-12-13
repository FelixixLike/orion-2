<?php

namespace App\Support\Auth;

use App\Domain\User\Config\UserModulePermissions;

/**
 * Resuelve dependencias de permisos automáticamente.
 * 
 * Este servicio asegura que cuando se asigna un permiso,
 * todos sus permisos dependientes también se asignan.
 */
class PermissionResolver
{
    /**
     * Resuelve las dependencias de un conjunto de permisos.
     *
     * @param array $permissions Permisos seleccionados
     * @return array Permisos con dependencias resueltas
     */
    public static function resolve(array $permissions): array
    {
        $resolved = $permissions;
        $dependencies = UserModulePermissions::dependencies();
        
        // Agregar dependencias hacia arriba (si marco hijo, marco padres)
        foreach ($permissions as $permission) {
            if (isset($dependencies[$permission])) {
                $resolved = array_merge($resolved, $dependencies[$permission]);
            }
        }
        
        return array_values(array_unique($resolved));
    }

    /**
     * Verifica si un permiso tiene dependencias no satisfechas.
     *
     * @param string $permission Permiso a verificar
     * @param array $currentPermissions Permisos actuales
     * @return array Dependencias faltantes
     */
    public static function getMissingDependencies(string $permission, array $currentPermissions): array
    {
        $dependencies = UserModulePermissions::dependencies();
        
        if (!isset($dependencies[$permission])) {
            return [];
        }
        
        return array_diff($dependencies[$permission], $currentPermissions);
    }

    /**
     * Obtiene todos los permisos que dependen de uno dado.
     *
     * @param string $permission Permiso base
     * @return array Permisos que dependen del dado
     */
    public static function getDependents(string $permission): array
    {
        $dependencies = UserModulePermissions::dependencies();
        $dependents = [];
        
        foreach ($dependencies as $perm => $deps) {
            if (in_array($permission, $deps)) {
                $dependents[] = $perm;
            }
        }
        
        return $dependents;
    }
}

