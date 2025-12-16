<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
declare(strict_types=1);

namespace App\Domain\User\Config;

final class UserModulePermissions
{
    /** @return array<int, string> */
    public static function all(): array
    {
        return array_keys(self::options());
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            'users.view' => 'Modulo: Usuarios (Ver)',
            'users.create' => 'Modulo: Usuarios (Crear)',
            'users.update' => 'Modulo: Usuarios (Editar)',
            'users.reset_password' => 'Modulo: Usuarios (Reset Password)',
            'users.update_status' => 'Modulo: Usuarios (Estado)',
            'users.assign_roles' => 'Modulo: Usuarios (Roles)',
            'users.assign_permissions' => 'Modulo: Usuarios (Permisos)',
            'operators.view' => 'Modulo: Reportes Operador',
            'imports.view' => 'Modulo: Importación',
            'retailers.view' => 'Modulo: Tenderos',
            'stores.view' => 'Modulo: Tiendas',
            'sales_conditions.view' => 'Modulo: Condiciones SIM',
            'settings.view' => 'Modulo: Configuraciones',
            'management_reports.view' => 'Modulo: Reporte Gerencial',
            'liquidations.view' => 'Modulo: Liquidaciones',
            'redemptions.view' => 'Modulo: Redenciones (Ver)',
            'redemptions.edit' => 'Modulo: Redenciones (Editar)',
            'redemption_products.view' => 'Modulo: Productos Redimibles (Ver)',
            'redemption_products.create' => 'Modulo: Productos Redimibles (Crear)',
            'redemption_products.edit' => 'Modulo: Productos Redimibles (Editar)',
            'redemption_products.delete' => 'Modulo: Productos Redimibles (Eliminar)',
            // 'balance.view' => 'Modulo: Estado de Cuenta', // Si aplica
        ];
    }

    /** @return array<string, string> */
    public static function descriptions(): array
    {
        return [
            'users.view' => 'Acceso al módulo de administración de usuarios del sistema.',
            'users.create' => 'Permite crear nuevos usuarios.',
            'users.update' => 'Permite editar información básica de usuarios.',
            'users.reset_password' => 'Permite restablecer contraseñas.',
            'users.update_status' => 'Permite activar/desactivar usuarios.',
            'users.assign_roles' => 'Permite asignar roles a usuarios.',
            'users.assign_permissions' => 'Permite asignar permisos específicos.',
            'operators.view' => 'Acceso a cruces y reportes de operador.',
            'imports.view' => 'Acceso al módulo de importación de archivos.',
            'retailers.view' => 'Acceso a la gestión de tenderos (Retailers).',
            'stores.view' => 'Acceso a la gestión de tiendas.',
            'sales_conditions.view' => 'Acceso a las condiciones comerciales de SIMs.',
            'settings.view' => 'Acceso a las configuraciones generales del sistema.',
            'management_reports.view' => 'Acceso a reportes gerenciales.',
            'liquidations.view' => 'Acceso al módulo de liquidaciones.',
            'redemptions.view' => 'Acceso al módulo de redenciones y catálogo de premios.',
        ];
    }

    /** @return array<string, array<int, string>> */
    public static function dependencies(): array
    {
        // Ya no hay dependencias complejas, cada permiso es independiente para su módulo.
        return [];
    }
}

