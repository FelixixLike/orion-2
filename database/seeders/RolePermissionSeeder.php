<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Limpiar cache de permisos
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Obtener todos los permisos definidos en la configuracion nueva (solo modulos view)
        $allPerms = \App\Domain\User\Config\UserModulePermissions::all();

        // Crear permisos si no existen
        foreach ($allPerms as $slug) {
            Permission::firstOrCreate(['name' => $slug, 'guard_name' => 'admin']);
        }

        // Crear permiso de edicion de redenciones si no existe
        // YA NO ES NECESARIO: Se crea automáticamente en el loop de arriba porque esta en UserModulePermissions

        // Roles
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'admin']);
        $admin = Role::firstOrCreate(['name' => 'administrator', 'guard_name' => 'admin']);
        $tat = Role::firstOrCreate(['name' => 'tat_direction', 'guard_name' => 'admin']);

        // SUPER ADMIN: todo
        $superAdmin->syncPermissions(Permission::where('guard_name', 'admin')->pluck('name')->toArray());

        // ADMIN: Todo el negocio excepto el módulo de usuarios, redenciones y productos redimibles
        $adminPerms = array_values(array_filter($allPerms, fn($p) => !in_array($p, [
            'users.view',
            'users.create',
            'users.update',
            'users.reset_password',
            'users.update_status',
            'users.assign_roles',
            'users.assign_permissions',
            'settings.view',
            'redemptions.view',
            'redemptions.edit',
            'redemption_products.view',
            'redemption_products.create',
            'redemption_products.edit',
            'redemption_products.delete',
        ])));

        $admin->syncPermissions($adminPerms);

        // TAT: Solo Tiendas, Condiciones, Redenciones, Tenderos, Productos Redimibles
        $tat->syncPermissions([
            'stores.view',
            'sales_conditions.view',
            'redemptions.view',
            'redemptions.edit',
            'retailers.view',
            'imports.view',
            'redemption_products.view',
            'redemption_products.create',
            'redemption_products.edit',
            'redemption_products.delete'
        ]);

        // Limpiar cache despues de crear permisos
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
