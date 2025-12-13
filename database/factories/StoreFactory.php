<?php

namespace Database\Factories;

use App\Domain\Store\Enums\Municipality;
use App\Domain\Store\Enums\StoreCategory;
use App\Domain\Store\Enums\StoreStatus;
use App\Domain\Store\Models\Store;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StoreFactory extends Factory
{
    protected $model = Store::class;

    public function definition(): array
    {
        return [
            'idpos' => fake()->unique()->numerify('#####'),
            'id_pdv' => fake()->unique()->numerify('#####'),
            'name' => fake()->company(),
            // 'user_id' => User::factory(), // Opcional, legacy?
            'category' => StoreCategory::ORO, // Asumiendo uno valido
            'phone' => fake()->phoneNumber(),
            'municipality' => Municipality::BOGOTA, // Asumiendo uno valido
            'address' => fake()->address(),
            'status' => StoreStatus::ACTIVE,
            'email' => fake()->unique()->safeEmail(),
        ];
    }
}
