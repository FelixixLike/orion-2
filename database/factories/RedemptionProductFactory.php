<?php

namespace Database\Factories;

use App\Domain\Store\Models\RedemptionProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RedemptionProduct>
 */
class RedemptionProductFactory extends Factory
{
    protected $model = RedemptionProduct::class;

    public function definition(): array
    {
        $types = [
            'sim' => ['SIM prepago', 'SIM pospago', 'SIM IoT'],
            'recharge' => ['Recarga 10K', 'Recarga 20K', 'Recarga 50K'],
            'device' => ['Equipo gama baja', 'Equipo gama media', 'Equipo gama alta'],
            'accessory' => ['Audífonos', 'Forro protector', 'Cargador rápido'],
        ];

        $type = fake()->randomElement(array_keys($types));
        $name = fake()->randomElement($types[$type]);

        return [
            'name' => $name,
            'type' => $type,
            'sku' => strtoupper(fake()->bothify('SKU-###??')),
            'unit_value' => fake()->randomElement([10000, 20000, 50000, 120000, 250000]),
            'is_active' => true,
        ];
    }
}
