<?php

namespace Database\Factories;

use App\Domain\Store\Models\Redemption;
use App\Domain\Store\Models\RedemptionProduct;
use App\Domain\Store\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Redemption>
 */
class RedemptionFactory extends Factory
{
    protected $model = Redemption::class;

    public function definition(): array
    {
        $storeId = Store::inRandomOrder()->value('id') ?? Store::factory();
        $productId = RedemptionProduct::inRandomOrder()->value('id') ?? RedemptionProduct::factory();
        $quantity = fake()->numberBetween(1, 5);
        $total = fake()->randomElement([10000, 20000, 50000, 120000]) * $quantity;
        $statuses = ['pending', 'approved', 'delivered', 'confirmed', 'rejected'];
        $statuses = ['pending', 'approved', 'delivered', 'confirmed', 'rejected'];

        $requestedAt = fake()->dateTimeBetween('-3 months', 'now');

        return [
            'store_id' => $storeId,
            'liquidation_id' => null,
            'redemption_product_id' => $productId,
            'quantity' => $quantity,
            'total_value' => $total,
            'requested_at' => $requestedAt,
            'status' => fake()->randomElement($statuses),
            'handled_by_user_id' => null,
            'notes' => fake()->boolean(20) ? fake()->sentence() : null,
        ];
    }
}
