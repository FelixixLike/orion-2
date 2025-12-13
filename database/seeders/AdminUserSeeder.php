<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domain\User\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admins = [
            [
                'username' => 'superadmin',
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'email' => 'superadmin@orion.com',
                'id_type' => 'CC',
                'id_number' => '0000000000',
                'password' => 'SuperAdmin2025!',
                'role' => 'super_admin',
            ],
            [
                'username' => 'admin',
                'first_name' => 'Regular',
                'last_name' => 'Administrator',
                'email' => 'admin@orion.com',
                'id_type' => 'CC',
                'id_number' => '5555555555',
                'password' => 'Admin2025!',
                'role' => 'administrator',
            ],
        ];

        foreach ($admins as $adminData) {
            $roleName = $adminData['role'];
            $password = $adminData['password'];
            unset($adminData['role'], $adminData['password']);

            // No depender del ID (puede existir otro usuario en id=1).
            // Usar username/email como clave y sobre-escribir campos críticos.
            $user = User::updateOrCreate(
                [
                    'username' => $adminData['username'],
                ],
                array_merge($adminData, [
                    'password_hash' => Hash::make($password),
                    'status' => 'active',
                    'email_verified_at' => now(), // Email pre-verificado
                    'password_changed_at' => now(), // Contrasena ya establecida
                    'must_change_password' => false, // No forzar cambio
                ])
            );

            // Crear el rol si no existe
            $role = Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'admin']
            );

            // Asignar rol
            if (!$user->hasRole($roleName, 'admin')) {
                // Si es super_admin, asegurar permisos sync
                if ($roleName === 'super_admin') {
                    // Darle TODOS los permisos al rol super_admin
                    $allPermissions = Permission::where('guard_name', 'admin')->get();
                    $role->syncPermissions($allPermissions);
                }

                $user->assignRole($role);
            }

            $this->command->info("[OK] Usuario {$adminData['username']} ({$roleName}) creado/actualizado exitosamente");
        }

        // Actualizar la secuencia de PostgreSQL para evitar conflictos
        // Cuando se fuerza un ID, la secuencia no se actualiza automaticamente
        DB::statement("SELECT setval('users_id_seq', (SELECT MAX(id) FROM users))");

        $this->command->info('==================================================');
        $this->command->info('Usuarios Admin creados: superadmin, admin');
        $this->command->warn('[!] IMPORTANTE: Cambia la contrasena despues del primer login');
    }
}
