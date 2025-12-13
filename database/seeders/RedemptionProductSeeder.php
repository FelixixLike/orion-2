<?php

namespace Database\Seeders;

use App\Domain\Store\Models\RedemptionProduct;
use Illuminate\Database\Seeder;

class RedemptionProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'name' => 'Recarga Minutos',
                'type' => 'recharge', // Tipo recarga
                'unit_value' => 0, // Sin valor fijo
                'max_value' => 500000, // Tope máximo por transacción
                'image_source' => 'default',
                'image_url' => 'images/store/recargas.png', // Imagen alusiva
                'is_active' => true,
            ],
            [
                'name' => 'Simcard Claro',
                'type' => 'sim',
                'unit_value' => 1000,
                'monthly_store_limit' => 2000, // Max 2000 al mes
                'image_source' => 'default',
                'image_url' => 'images/store/simcard.png', // Imagen alusiva
                'is_active' => true,
            ],
            [
                'name' => 'Cargador 65W',
                'type' => 'accessory',
                'unit_value' => 70000,
                'stock' => 20, // Solo 20 unidades
                'image_source' => 'default',
                'image_url' => 'images/store/item.png', // Imagen alusiva
                'is_active' => true,
            ],
        ];

        foreach ($products as $data) {
            // Separamos campos que no son del modelo directo si fuera necesario, 
            // pero aqui todos parecen mapear bien o ser ignorados si no están en fillable.
            // Ajustamos según lógica de negocio si es necesario.

            // image_source es un campo de UI del formulario filament, no de BD. 
            // image_url si está en BD (ver migration 2025_12_05...).
            $imageSource = $data['image_source'];
            unset($data['image_source']);

            RedemptionProduct::updateOrCreate(
                ['name' => $data['name']],
                $data
            );
        }

        $this->command->info('Productos de redención creados exitosamente.');
    }
}
