<?php

namespace Database\Seeders;

use App\Domain\Store\Models\Store;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $stores = [
            [
                'id_pdv' => '14413',
                'idpos' => '14413',
                'route_code' => 'R5METMOV002',
                'circuit_code' => 'R5METMOVSS302',
                'name' => 'INTERMOVIL JM',
                'category' => 'platino',
                'phone' => '3204701367',
                'municipality' => 'cumaral',
                'neighborhood' => 'CENTRO',
                'address' => 'CR 20 12 22 LC 2',
                'email' => 'INTERNET.5001C@GMAIL.COM',
                'retailer_cedula' => '9876543210',
            ],
            [
                'id_pdv' => '14429',
                'idpos' => '14429',
                'route_code' => 'R5METMOV002',
                'circuit_code' => 'R5METMOVSS502',
                'name' => 'PAPELERIA KEVIN',
                'category' => 'plata',
                'phone' => '3135364237',
                'municipality' => 'villavicencio',
                'neighborhood' => 'CHAPINERITO',
                'address' => 'CL 46 C 52 44',
                'email' => 'LIZETHSOSA941@GMAIL.COM',
                'retailer_cedula' => '9876543210',
            ],
            [
                'id_pdv' => '14499',
                'idpos' => '14499',
                'route_code' => 'R5METMOV006',
                'circuit_code' => 'R5METMOVSS206',
                'name' => 'PAPELERIA SAMY',
                'category' => 'oro',
                'phone' => '3059004682',
                'municipality' => 'villavicencio',
                'neighborhood' => 'PORFIA',
                'address' => 'CL 64 SUR 45 99',
                'email' => 'PAPELERIASAMII@AIL.COM',
                'retailer_cedula' => '100200300',
            ],
        ];

        foreach ($stores as $data) {
            $retailerCedula = $data['retailer_cedula'];
            unset($data['retailer_cedula']); // Remove from data array before creation

            // Ensure lowercase for enum types if needed, matching seeder conventions
            $data['municipality'] = strtolower($data['municipality']);
            $data['category'] = strtolower($data['category']);
            $data['status'] = 'active';

            $store = Store::updateOrCreate(
                ['idpos' => $data['idpos']],
                $data
            );

            // Assign to retailer if exists
            $retailer = \App\Domain\User\Models\User::where('id_number', $retailerCedula)->first();
            if ($retailer) {
                // Attach store to user via pivot
                $retailer->stores()->syncWithoutDetaching([$store->id]);
                $this->command->info("Tienda {$store->name} (IDPOS: {$store->idpos}) asignada a retailer {$retailer->username}");
            } else {
                $this->command->warn("No se encontró retailer con cedula {$retailerCedula} para la tienda {$store->name}");
            }
        }
    }
}
