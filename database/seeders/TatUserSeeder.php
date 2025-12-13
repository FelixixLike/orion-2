<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domain\User\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class TatUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // No depender de ID; usar username/email como claves naturales.
        $tat = User::updateOrCreate(
            ['username' => 'tat'],
            [
                'first_name' => 'Tat',
                'last_name' => 'Direction',
                'email' => 'tat@orion.com',
                'phone' => '+573001111111',
                'id_type' => 'CC',
                'id_number' => '123450001',
                'password_hash' => Hash::make('Tat2025!'),
                'status' => 'active',
                'email_verified_at' => now(),
                'password_changed_at' => now(),
                'must_change_password' => false,
            ]
        );

        $tatRole = Role::firstOrCreate(['name' => 'tat_direction', 'guard_name' => 'admin']);

        if (! $tat->hasRole($tatRole->name, $tatRole->guard_name)) {
            $tat->assignRole($tatRole);
        }

        $this->command->info('[OK] Usuario TAT creado: tat@orion.com / Tat2025!');
    }
}
