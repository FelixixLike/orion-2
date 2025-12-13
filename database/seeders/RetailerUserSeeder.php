<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domain\User\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class RetailerUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $retailerRole = Role::firstOrCreate(['name' => 'retailer', 'guard_name' => 'retailer']);

        $users = [
            [
                'username' => '9876543210',
                'first_name' => 'Juan',
                'last_name' => 'Perez',
                'email' => 'retailer@orion.com',
                'phone' => '+573001234567',
                'id_type' => 'CC',
                'id_number' => '9876543210',
                'password' => 'password',
            ],
            [
                'username' => '100200300',
                'first_name' => 'Maria',
                'last_name' => 'Gomez',
                'email' => 'retailer2@orion.com',
                'phone' => '+573007654321',
                'id_type' => 'CC',
                'id_number' => '100200300',
                'password' => 'password',
            ],
        ];

        foreach ($users as $userData) {
            $password = $userData['password'];
            unset($userData['password']);

            // No depender de ID; usar username/email como claves naturales.
            $retailer = User::updateOrCreate(
                ['username' => $userData['username']],
                array_merge($userData, [
                    'password_hash' => Hash::make($password),
                    'status' => 'active',
                    'email_verified_at' => now(), // Email pre-verificado (usuario de testing)
                    'password_changed_at' => now(), // Contrasena ya establecida
                    'must_change_password' => false, // No forzar cambio (usuario de testing)
                ])
            );

            // Asignar rol de retailer (con guard retailer)
            if (!$retailer->hasRole($retailerRole->name, $retailerRole->guard_name)) {
                $retailer->assignRole($retailerRole);
            }

            $this->command->info("Usuario retailer de prueba creado/actualizado: {$userData['username']}");
        }

        $this->command->info('==================================================');
        $this->command->info('Usuarios creados: retailer, retailer2');
        $this->command->info('Password por defecto: password');
        $this->command->info('==================================================');
        $this->command->warn('Estos son usuarios de prueba con contrasena ya establecida');
        $this->command->warn('Los nuevos retailers creados desde Filament reciben email de activacion');
    }
}
