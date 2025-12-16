<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domain\Store\Models\StoreCategory;
use Illuminate\Support\Str;

class StoreCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Oro', 'slug' => 'oro', 'color' => 'warning'],
            ['name' => 'Plata', 'slug' => 'plata', 'color' => 'secondary'],
            ['name' => 'Platino', 'slug' => 'platino', 'color' => 'primary'],
            ['name' => 'Bronce', 'slug' => 'bronce', 'color' => 'danger'],
            ['name' => 'Diamante', 'slug' => 'diamante', 'color' => 'info'],
        ];

        foreach ($categories as $cat) {
            StoreCategory::updateOrCreate(
                ['name' => $cat['name']], // Match by name
                [
                    'slug' => $cat['slug'],
                    'color' => $cat['color']
                ]
            );
        }
    }
}
