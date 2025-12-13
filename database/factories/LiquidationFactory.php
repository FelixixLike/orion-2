<?php

namespace Database\Factories;

use App\Domain\Store\Models\Liquidation;
use App\Domain\Store\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Liquidation>
 */
class LiquidationFactory extends Factory
{
    protected $model = Liquidation::class;

    public function definition(): array
    {
        $periodDate = fake()->dateTimeBetween('-3 months', 'now');

        $gross = fake()->numberBetween(80000, 200000);
        $net = $gross - fake()->numberBetween(5000, 20000);

        return [
            'store_id' => Store::inRandomOrder()->value('id') ?? Store::factory(),
            'period_year' => (int) $periodDate->format('Y'),
            'period_month' => (int) $periodDate->format('n'),
            'version' => 1,
            'gross_amount' => $gross,
            'net_amount' => $net,
            'status' => fake()->randomElement(['draft', 'closed']),
            'clarifications' => fake()->boolean(30) ? fake()->sentence() : null,
        ];
    }
}
