<?php

declare(strict_types=1);

namespace App\Domain\User\Constants;

/**
 * Permisos del sistema.
 * 
 * Estos valores deben coincidir con los definidos en PermissionSeeder.
 */
class Permission
{
    // Gestión de Usuarios
    public const VIEW_USERS = 'view_users';
    public const CREATE_USERS = 'create_users';
    public const EDIT_USERS = 'edit_users';
    public const DELETE_USERS = 'delete_users';
    public const MANAGE_USER_ROLES = 'manage_user_roles';

    // Gestión de Importaciones
    public const VIEW_IMPORTS = 'view_imports';
    public const CREATE_IMPORTS = 'create_imports';
    public const DELETE_IMPORTS = 'delete_imports';

    // Reportes
    public const VIEW_OPERATOR_REPORTS = 'view_operator_reports';
    public const EXPORT_OPERATOR_REPORTS = 'export_operator_reports';

    // Recargas
    public const VIEW_RECHARGES = 'view_recharges';
    public const EXPORT_RECHARGES = 'export_recharges';

    // Condiciones de Venta
    public const VIEW_SALES_CONDITIONS = 'view_sales_conditions';
    public const EXPORT_SALES_CONDITIONS = 'export_sales_conditions';

    // Configuración
    public const MANAGE_SETTINGS = 'manage_settings';
    public const MANAGE_MAIL_SETTINGS = 'manage_mail_settings';

    /**
     * Obtiene todos los permisos definidos.
     * 
     * @return array<string>
     */
    public static function all(): array
    {
        $reflection = new \ReflectionClass(self::class);
        return array_values($reflection->getConstants());
    }

    /**
     * Obtiene permisos agrupados por módulo.
     * 
     * @return array<string, array<string>>
     */
    public static function grouped(): array
    {
        return [
            'Usuarios' => [
                self::VIEW_USERS,
                self::CREATE_USERS,
                self::EDIT_USERS,
                self::DELETE_USERS,
                self::MANAGE_USER_ROLES,
            ],
            'Importaciones' => [
                self::VIEW_IMPORTS,
                self::CREATE_IMPORTS,
                self::DELETE_IMPORTS,
            ],
            'Reportes del Operador' => [
                self::VIEW_OPERATOR_REPORTS,
                self::EXPORT_OPERATOR_REPORTS,
            ],
            'Recargas' => [
                self::VIEW_RECHARGES,
                self::EXPORT_RECHARGES,
            ],
            'Condiciones de Venta' => [
                self::VIEW_SALES_CONDITIONS,
                self::EXPORT_SALES_CONDITIONS,
            ],
            'Configuración' => [
                self::MANAGE_SETTINGS,
                self::MANAGE_MAIL_SETTINGS,
            ],
        ];
    }
}
